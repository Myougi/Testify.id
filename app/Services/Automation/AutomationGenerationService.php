<?php

namespace App\Services\Automation;

use App\Jobs\DispatchAutomationRun;
use App\Jobs\GenerateAutomationDefinition;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\ProjectEnvironment;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AutomationGenerationService
{
    /** @var list<string> */
    private const AGENTIC_FAILURE_CODES = [
        'locator_not_found',
        'locator_ambiguous',
        'assertion_failed',
        'navigation_failed',
        'step_timeout',
    ];

    public function __construct(private readonly AutomationDefinitionService $definitions) {}

    public function dispatch(
        TestCase $testCase,
        string $environment,
        User $user,
        ?string $prompt = null,
    ): AutomationRun {
        if (! config('automation.enabled', false)) {
            throw ValidationException::withMessages(['automation' => 'Browser automation is not enabled.']);
        }

        $projectEnvironment = $this->environment($testCase, $environment);
        $run = $this->createRun(
            $testCase,
            $projectEnvironment,
            $user,
            $prompt,
            AutomationRun::PHASE_STATIC,
            'laravel_queue',
        );

        GenerateAutomationDefinition::dispatch($run->id)->afterCommit();

        return $run;
    }

    public function processStatic(string $runId): void
    {
        $run = AutomationRun::query()
            ->with(['executions.testCase.steps', 'executions.testCase.project.credentialProfiles'])
            ->findOrFail($runId);

        if ($run->kind !== AutomationRun::KIND_GENERATION || $this->isFinal($run)) {
            return;
        }

        $execution = $run->executions->firstOrFail();
        $testCase = $execution->testCase;
        $run->forceFill([
            'status' => AutomationRun::STATUS_RUNNING,
            'phase' => AutomationRun::PHASE_STATIC,
            'progress_message' => AutomationRun::PHASE_STATIC,
            'started_at' => $run->started_at ?? now(),
        ])->save();
        $execution->forceFill([
            'status' => AutomationExecution::STATUS_RUNNING,
            'started_at' => $execution->started_at ?? now(),
        ])->save();

        try {
            $result = $this->definitions->generate($testCase);
            $automation = $result['automation'];
            $automation->forceFill([
                'generation_source' => AutomationRun::SOURCE_STATIC,
                'generation_run_id' => $run->id,
            ])->save();
            $execution->forceFill([
                'test_case_automation_id' => $automation->id,
                'definition_snapshot' => $automation->definition,
                'status' => AutomationExecution::STATUS_PASSED,
                'completed_at' => now(),
            ])->save();
            $run->forceFill([
                'generation_source' => AutomationRun::SOURCE_STATIC,
                'warnings' => $result['warnings'],
                'status' => AutomationRun::STATUS_COMPLETED,
                'phase' => AutomationRun::SOURCE_STATIC,
                'progress_message' => 'Static automation draft generated.',
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $summary = $this->summary($exception);

            if (! $this->agenticEnabled() || ! $this->isStaticOutputFailure($exception)) {
                if ($exception instanceof ValidationException) {
                    $this->fail($run->id, $exception, 'generation_validation_error');

                    return;
                }

                throw $exception;
            }

            $this->transitionToAgentic($run, $summary);
        }
    }

    public function dispatchAgenticFallback(AutomationExecution $sourceExecution): ?AutomationRun
    {
        if (! $this->agenticEnabled()
            || ! in_array($sourceExecution->failure_code, self::AGENTIC_FAILURE_CODES, true)) {
            return null;
        }

        $sourceExecution->loadMissing(['automationRun.requester', 'testCase.steps', 'testCase.project.environments']);
        $sourceRun = $sourceExecution->automationRun;
        $testCase = $sourceExecution->testCase;
        $sourceHash = $this->definitions->sourceHash($testCase);
        $alreadyDispatched = AutomationRun::query()
            ->where('kind', AutomationRun::KIND_GENERATION)
            ->where('generation_source', AutomationRun::SOURCE_AGENTIC)
            ->where('source_hash', $sourceHash)
            ->whereHas('executions', fn ($query) => $query->where('test_case_id', $testCase->id))
            ->exists();

        if ($alreadyDispatched || ! $sourceRun->requester) {
            return null;
        }

        try {
            $projectEnvironment = $this->environment($testCase, $sourceRun->environment);
        } catch (ValidationException) {
            return null;
        }
        $run = $this->createRun(
            $testCase,
            $projectEnvironment,
            $sourceRun->requester,
            null,
            AutomationRun::PHASE_STARTING_BROWSER,
            'github_actions',
            $sourceRun,
        );
        $this->transitionToAgentic($run, 'Browser validation could not be repaired deterministically.');

        return $run->fresh(['executions.testCase']);
    }

    public function progress(AutomationRun $run, string $phase, ?string $message): AutomationRun
    {
        if ($run->kind !== AutomationRun::KIND_GENERATION || $this->isFinal($run)) {
            throw ValidationException::withMessages(['run' => 'The generation run cannot accept progress updates.']);
        }

        $run->forceFill([
            'phase' => $phase,
            'progress_message' => $this->limited($message, 500) ?? $phase,
        ])->save();

        return $run->fresh();
    }

    /** @param array<string, mixed> $result */
    public function completeAgentic(AutomationExecution $execution, array $result): AutomationExecution
    {
        $execution->loadMissing(['automationRun', 'testCase.steps']);
        $run = $execution->automationRun;

        if ($run->kind !== AutomationRun::KIND_GENERATION
            || $run->generation_source !== AutomationRun::SOURCE_AGENTIC) {
            throw ValidationException::withMessages(['run' => 'This run is not an agentic generation run.']);
        }

        if (in_array($execution->status, [AutomationExecution::STATUS_PASSED, AutomationExecution::STATUS_FAILED], true)) {
            return $execution;
        }

        if (! ($result['success'] ?? false)) {
            $message = $this->limited($result['failure_message'] ?? null, 10000) ?? 'Browser exploration did not produce a definition.';
            $execution->forceFill([
                'status' => AutomationExecution::STATUS_FAILED,
                'failure_type' => 'generation',
                'failure_code' => $this->limited($result['failure_code'] ?? null, 60),
                'failure_message' => $message,
                'duration_ms' => isset($result['duration_ms']) ? max(0, (int) $result['duration_ms']) : null,
                'completed_at' => now(),
            ])->save();
            $run->forceFill([
                'status' => AutomationRun::STATUS_FAILED,
                'phase' => AutomationRun::PHASE_FAILED,
                'progress_message' => $message,
                'failure_code' => $execution->failure_code,
                'failure_message' => $message,
                'completed_at' => now(),
            ])->save();

            return $execution->fresh();
        }

        if ($run->source_hash !== $this->definitions->sourceHash($execution->testCase)) {
            throw ValidationException::withMessages([
                'definition' => 'The Test Case changed while browser exploration was running.',
            ]);
        }

        $definition = is_array($result['definition'] ?? null) ? $result['definition'] : [];

        try {
            $validated = $this->definitions->compileDefinition($execution->testCase, $definition, $run->environment);
            $automation = $this->definitions->save(
                $execution->testCase,
                $validated,
                generated: true,
                generationSource: AutomationRun::SOURCE_AGENTIC,
                generationRunId: $run->id,
            );
        } catch (ValidationException $exception) {
            $message = $this->summary($exception);
            $execution->forceFill([
                'status' => AutomationExecution::STATUS_FAILED,
                'failure_type' => 'generation',
                'failure_code' => 'generation_validation_error',
                'failure_message' => $message,
                'duration_ms' => isset($result['duration_ms']) ? max(0, (int) $result['duration_ms']) : null,
                'completed_at' => now(),
            ])->save();
            $run->forceFill([
                'status' => AutomationRun::STATUS_FAILED,
                'phase' => AutomationRun::PHASE_FAILED,
                'progress_message' => $message,
                'failure_code' => 'generation_validation_error',
                'failure_message' => $message,
                'completed_at' => now(),
            ])->save();

            return $execution->fresh();
        }

        $warnings = [];
        $resultWarnings = $result['warnings'] ?? null;

        if (is_array($resultWarnings)) {
            foreach ($resultWarnings as $warning) {
                if (is_string($warning) && trim($warning) !== '') {
                    $warnings[] = Str::limit(trim($warning), 255, '');
                }
            }
        }
        $execution->forceFill([
            'test_case_automation_id' => $automation->id,
            'definition_snapshot' => $validated,
            'status' => AutomationExecution::STATUS_PASSED,
            'duration_ms' => isset($result['duration_ms']) ? max(0, (int) $result['duration_ms']) : null,
            'completed_at' => now(),
        ])->save();
        $run->forceFill([
            'status' => AutomationRun::STATUS_COMPLETED,
            'phase' => AutomationRun::PHASE_DRAFT_RECORDED,
            'progress_message' => 'Browser-recorded draft created. Validate it before approval.',
            'warnings' => $warnings,
            'completed_at' => now(),
        ])->save();

        return $execution->fresh(['automation']);
    }

    public function fail(string $runId, Throwable $exception, string $failureCode = 'generation_infrastructure_error'): void
    {
        $run = AutomationRun::query()->with('executions')->find($runId);

        if (! $run || $this->isFinal($run)) {
            return;
        }

        $message = $this->summary($exception);
        $run->forceFill([
            'status' => AutomationRun::STATUS_ERROR,
            'phase' => AutomationRun::PHASE_FAILED,
            'progress_message' => $message,
            'failure_code' => $failureCode,
            'failure_message' => $message,
            'completed_at' => now(),
        ])->save();
        $run->executions()
            ->whereNotIn('status', [AutomationExecution::STATUS_PASSED, AutomationExecution::STATUS_FAILED])
            ->update([
                'status' => AutomationExecution::STATUS_BLOCKED,
                'failure_type' => 'infrastructure',
                'failure_code' => $failureCode,
                'failure_message' => $message,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function transitionToAgentic(AutomationRun $run, string $reason): void
    {
        $run->forceFill([
            'provider' => 'github_actions',
            'generation_source' => AutomationRun::SOURCE_AGENTIC,
            'status' => AutomationRun::STATUS_QUEUED,
            'phase' => AutomationRun::PHASE_NEEDS_GROUNDING,
            'progress_message' => Str::limit($reason, 500, ''),
            'failure_code' => null,
            'failure_message' => null,
            'completed_at' => null,
        ])->save();
        $run->executions()->update([
            'status' => AutomationExecution::STATUS_QUEUED,
            'failure_type' => null,
            'failure_code' => null,
            'failure_message' => null,
            'completed_at' => null,
            'updated_at' => now(),
        ]);
        DispatchAutomationRun::dispatch($run->id)->afterCommit();
    }

    private function createRun(
        TestCase $testCase,
        ProjectEnvironment $environment,
        User $user,
        ?string $prompt,
        string $phase,
        string $provider,
        ?AutomationRun $parentRun = null,
    ): AutomationRun {
        return DB::transaction(function () use ($testCase, $environment, $user, $prompt, $phase, $provider, $parentRun): AutomationRun {
            $latest = AutomationRun::query()
                ->where('kind', AutomationRun::KIND_GENERATION)
                ->whereHas('executions', fn ($query) => $query->where('test_case_id', $testCase->id))
                ->lockForUpdate()
                ->latest('attempt')
                ->first();

            if ($latest && in_array($latest->status, [
                AutomationRun::STATUS_QUEUED,
                AutomationRun::STATUS_DISPATCHING,
                AutomationRun::STATUS_RUNNING,
            ], true)) {
                throw ValidationException::withMessages([
                    'automation' => 'Automation generation is already in progress for this Test Case.',
                ]);
            }

            $run = AutomationRun::query()->create([
                'kind' => AutomationRun::KIND_GENERATION,
                'requested_by' => $user->id,
                'environment' => $environment->environment,
                'base_url' => rtrim($environment->base_url, '/'),
                'attempt' => ($latest->attempt ?? 0) + 1,
                'provider' => $provider,
                'status' => AutomationRun::STATUS_QUEUED,
                'goal_prompt' => $this->limited($prompt, (int) config('automation.agentic_generation.prompt_max_characters', 1000)),
                'phase' => $phase,
                'progress_message' => $phase,
                'model' => $this->agenticModel(),
                'source_hash' => $this->definitions->sourceHash($testCase),
                'parent_run_id' => $parentRun?->id,
            ]);
            $run->executions()->create([
                'test_case_id' => $testCase->id,
                'definition_snapshot' => null,
                'status' => AutomationExecution::STATUS_QUEUED,
            ]);

            return $run->load(['executions.testCase']);
        });
    }

    private function environment(TestCase $testCase, string $environment): ProjectEnvironment
    {
        if ($environment === TestRun::ENVIRONMENT_PRODUCTION) {
            throw ValidationException::withMessages([
                'environment' => 'Production cannot be used for automation generation.',
            ]);
        }

        $projectEnvironment = ProjectEnvironment::query()
            ->where('project_id', $testCase->project_id)
            ->where('environment', $environment)
            ->where('enabled', true)
            ->first();

        if (! $projectEnvironment || trim($projectEnvironment->base_url) === '') {
            throw ValidationException::withMessages([
                'environment' => "No enabled {$environment} URL is configured for this project.",
            ]);
        }

        return $projectEnvironment;
    }

    private function agenticEnabled(): bool
    {
        return (bool) config('automation.enabled', false)
            && (bool) config('automation.agentic_generation.enabled', false);
    }

    private function agenticModel(): string
    {
        return (string) (config('automation.agentic_generation.model')
            ?? config('automation.ai.model')
            ?? config('ai.providers.openrouter.models.text.cheapest')
            ?? config('ai.providers.openrouter.models.text.default'));
    }

    private function isStaticOutputFailure(Throwable $exception): bool
    {
        if ($exception instanceof ValidationException) {
            return true;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'structured output')
            || str_contains($message, 'automation definition')
            || str_contains($message, 'compiler');
    }

    private function isFinal(AutomationRun $run): bool
    {
        return in_array($run->status, [
            AutomationRun::STATUS_COMPLETED,
            AutomationRun::STATUS_FAILED,
            AutomationRun::STATUS_ERROR,
            AutomationRun::STATUS_CANCELLED,
        ], true);
    }

    private function summary(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            foreach ($exception->errors() as $messages) {
                if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                    return $this->limited($messages[0], 10000) ?? 'Automation generation failed validation.';
                }
            }

            return 'Automation generation failed validation.';
        }

        return $this->limited($exception->getMessage(), 10000) ?? 'Automation generation failed.';
    }

    private function limited(mixed $value, int $maximum): ?string
    {
        $value = trim(is_string($value) ? $value : '');

        return $value === '' ? null : mb_substr($value, 0, $maximum);
    }
}
