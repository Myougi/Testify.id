<?php

namespace App\Services\Automation;

use App\Ai\Agents\AutomationHealingAgent;
use App\Jobs\HealAutomationDefinition;
use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use App\Models\AutomationHealingAttempt;
use App\Models\AutomationRun;
use App\Models\TestCaseAutomation;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class AutomationHealingService
{
    /** @var list<string> */
    private const HEALABLE_FAILURE_CODES = [
        'locator_not_found',
        'locator_ambiguous',
        'assertion_failed',
        'navigation_failed',
        'step_timeout',
    ];

    public function __construct(
        private readonly AutomationDefinitionService $definitions,
    ) {}

    public function queueIfEligible(AutomationExecution $execution): bool
    {
        if (! config('automation.ai_healing.enabled', false)
            || (int) config('automation.ai_healing.max_attempts', 1) < 1
            || ! in_array($execution->failure_code, self::HEALABLE_FAILURE_CODES, true)
            || $execution->failed_step === null) {
            return false;
        }

        $execution->loadMissing(['automationRun', 'automation.testCase.steps', 'artifacts']);

        if ($execution->automationRun->kind !== AutomationRun::KIND_VALIDATION
            || AutomationHealingAttempt::query()->where('revalidation_run_id', $execution->automation_run_id)->exists()
            || AutomationHealingAttempt::query()->where('source_execution_id', $execution->id)->exists()
            || ! $execution->artifacts->contains(fn (AutomationArtifact $artifact): bool => $artifact->type === AutomationArtifact::TYPE_DIAGNOSTIC && $artifact->uploaded_at !== null)
            || $execution->automation === null) {
            return false;
        }

        $execution->automation->forceFill([
            'status' => TestCaseAutomation::STATUS_HEALING,
            'approval_source' => null,
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        HealAutomationDefinition::dispatch($execution->id)->afterCommit();

        return true;
    }

    public function heal(AutomationExecution $execution): ?AutomationHealingAttempt
    {
        $execution->loadMissing([
            'automationRun.requester',
            'automation.testCase.steps',
            'automation.testCase.project.credentialProfiles',
            'artifacts',
        ]);
        $automation = $execution->automation;

        if ($automation === null || $execution->failed_step === null) {
            return null;
        }

        $attempt = AutomationHealingAttempt::query()->firstOrCreate(
            ['source_execution_id' => $execution->id],
            [
                'test_case_automation_id' => $automation->id,
                'status' => AutomationHealingAttempt::STATUS_HEALING,
                'failed_step' => $execution->failed_step,
                'original_definition' => $execution->definition_snapshot,
                'source_hash' => (string) $automation->source_hash,
                'failure_message' => $execution->failure_message,
            ],
        );

        if (! $attempt->wasRecentlyCreated) {
            return $attempt;
        }

        try {
            $this->assertCurrentSource($automation, $execution);
            $diagnostic = $this->diagnostic($execution);
            $definition = is_array($execution->definition_snapshot) ? $execution->definition_snapshot : [];
            $stepIndex = $execution->failed_step - 1;
            $steps = $definition['steps'] ?? null;

            if (! is_array($steps)) {
                throw new RuntimeException('The failed automation definition does not contain a valid step list.');
            }

            $originalStep = $steps[$stepIndex] ?? null;

            if (! is_array($originalStep)) {
                throw new RuntimeException('The failed automation step is missing from its definition snapshot.');
            }

            $response = AutomationHealingAgent::make(context: [
                'test_case' => [
                    'case_key' => $automation->testCase->case_key,
                    'title' => $automation->testCase->title,
                    'preconditions' => $automation->testCase->preconditions,
                    'manual_steps' => $automation->testCase->steps->map(fn ($step): array => [
                        'position' => $step->position,
                        'action' => $step->action,
                        'expected_result' => $step->expected_result,
                    ])->values()->all(),
                ],
                'failed_step_number' => $execution->failed_step,
                'failed_step' => $originalStep,
                'failure_code' => $execution->failure_code,
                'failure_message' => $execution->failure_message,
                'browser_evidence' => $diagnostic,
                'credential_profiles' => $this->definitions->credentialProfiles(
                    $automation->testCase,
                    $execution->automationRun->environment,
                ),
            ])->prompt('Repair the failed Playwright DSL step using the browser evidence.');

            if (! $response instanceof StructuredAgentResponse) {
                throw new RuntimeException('The automation healer did not return structured output.');
            }

            $replacement = $this->definitions->compileStep($response->structured['replacement_step'] ?? null, $stepIndex);
            $definition['steps'][$stepIndex] = $replacement;
            $validated = $this->definitions->validateDefinition(
                $automation->testCase,
                $definition,
                $execution->automationRun->environment,
            );
            $this->assertCurrentSource($automation->fresh(), $execution);

            $automation->forceFill([
                'definition' => $validated,
                'status' => TestCaseAutomation::STATUS_HEALING,
                'last_validated_environment' => null,
                'last_validated_at' => null,
                'validation_summary' => 'AI produced a replacement step. Automatic browser revalidation is being queued.',
                'approval_source' => null,
                'approved_by' => null,
                'approved_at' => null,
                'last_healed_at' => now(),
            ])->save();

            $revalidation = app(AutomationDispatchService::class)->dispatchValidation(
                $automation->fresh(['testCase.project.environments', 'testCase.steps']),
                $execution->automationRun->environment,
                $execution->automationRun->requester,
            );
            $attempt->forceFill([
                'status' => AutomationHealingAttempt::STATUS_VALIDATING,
                'proposed_definition' => $validated,
                'rationale' => $this->limitedString($response->structured['rationale'] ?? null, 2000),
                'confidence' => max(0, min(100, (int) ($response->structured['confidence'] ?? 0))),
                'revalidation_run_id' => $revalidation->id,
            ])->save();
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => AutomationHealingAttempt::STATUS_FAILED,
                'failure_message' => $this->limitedString($exception->getMessage(), 10000),
                'completed_at' => now(),
            ])->save();
            $automation->forceFill([
                'status' => TestCaseAutomation::STATUS_INVALID,
                'validation_summary' => 'AI healing failed: '.$this->limitedString($exception->getMessage(), 500),
            ])->save();
        }

        return $attempt->fresh(['revalidationRun']);
    }

    public function completeRevalidation(AutomationRun $run, bool $passed): bool
    {
        $attempt = AutomationHealingAttempt::query()
            ->where('revalidation_run_id', $run->id)
            ->with('automation.testCase.steps')
            ->first();

        if (! $attempt) {
            return false;
        }

        $automation = $attempt->automation;
        $isCurrent = $automation->source_hash === $attempt->source_hash
            && $this->definitions->sourceHash($automation->testCase) === $attempt->source_hash
            && $automation->definition === $attempt->proposed_definition;

        if ($passed && $isCurrent) {
            $automation->forceFill([
                'status' => TestCaseAutomation::STATUS_READY,
                'approval_source' => TestCaseAutomation::APPROVAL_AI_HEALED,
                'approved_by' => null,
                'approved_at' => now(),
                'validation_summary' => 'AI-healed definition passed automatic browser revalidation.',
            ])->save();
            $attempt->forceFill([
                'status' => AutomationHealingAttempt::STATUS_APPLIED,
                'completed_at' => now(),
            ])->save();
        } else {
            $attempt->forceFill([
                'status' => $isCurrent ? AutomationHealingAttempt::STATUS_FAILED : AutomationHealingAttempt::STATUS_CANCELLED,
                'failure_message' => $isCurrent
                    ? 'The healed definition did not pass automatic browser revalidation.'
                    : 'The Test Case or automation definition changed during healing.',
                'completed_at' => now(),
            ])->save();
        }

        return true;
    }

    private function assertCurrentSource(TestCaseAutomation $automation, AutomationExecution $execution): void
    {
        $currentDefinition = $automation->definition;
        $snapshotDefinition = $execution->definition_snapshot;

        if (! is_array($currentDefinition) || ! is_array($snapshotDefinition)) {
            throw new RuntimeException('The Test Case or automation definition changed after validation started.');
        }

        if ($automation->source_hash !== $this->definitions->sourceHash($automation->testCase)
            || $currentDefinition !== $snapshotDefinition) {
            throw new RuntimeException('The Test Case or automation definition changed after validation started.');
        }
    }

    /** @return array<string, mixed> */
    private function diagnostic(AutomationExecution $execution): array
    {
        $artifact = $execution->artifacts
            ->where('type', AutomationArtifact::TYPE_DIAGNOSTIC)
            ->whereNotNull('uploaded_at')
            ->sortByDesc('id')
            ->first();

        if (! $artifact) {
            throw new RuntimeException('No sanitized browser diagnostic is available for healing.');
        }

        $contents = Storage::disk($artifact->disk)->get($artifact->path);
        $maximum = (int) config('automation.ai_healing.diagnostic_max_characters', 20000);
        $decoded = json_decode(mb_substr($contents, 0, $maximum), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The browser diagnostic artifact is invalid.');
        }

        return $decoded;
    }

    private function limitedString(mixed $value, int $maximum): string
    {
        return mb_substr(is_string($value) ? trim($value) : '', 0, $maximum);
    }
}
