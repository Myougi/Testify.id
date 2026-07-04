<?php

namespace App\Services\Automation;

use App\Ai\Agents\AutomationDefinitionAgent;
use App\Models\ProjectCredentialProfile;
use App\Models\TestCase;
use App\Models\TestCaseAutomation;
use App\Models\TestRun;
use App\Models\User;
use App\Support\Automation\AutomationDefinitionValidator;
use App\Support\Automation\AutomationStepCompiler;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class AutomationDefinitionService
{
    public function __construct(
        private readonly AutomationDefinitionValidator $validator,
        private readonly AutomationStepCompiler $compiler,
    ) {}

    /**
     * @return array{automation: TestCaseAutomation, warnings: list<string>}
     */
    public function generate(TestCase $testCase): array
    {
        $testCase->loadMissing(['project.credentialProfiles', 'module', 'steps']);
        $profiles = $this->credentialProfiles($testCase);
        $context = [
            'case_key' => $testCase->case_key,
            'title' => $testCase->title,
            'preconditions' => $testCase->preconditions,
            'module' => $testCase->module?->name,
            'steps' => $testCase->steps->map(fn ($step): array => [
                'position' => $step->position,
                'action' => $step->action,
                'expected_result' => $step->expected_result,
            ])->all(),
            'credential_profiles' => $profiles,
        ];
        $structured = $this->generateStructured($context);

        try {
            $definition = $this->compileAndValidate(
                is_array($structured['definition'] ?? null) ? $structured['definition'] : [],
                $profiles,
            );
        } catch (ValidationException $exception) {
            $structured = $this->generateStructured([
                ...$context,
                'previous_output' => $structured['definition'] ?? null,
                'correction_errors' => $exception->errors(),
            ], 'Correct the previous output using every compiler error. Return a complete replacement definition.');
            $definition = $this->compileAndValidate(
                is_array($structured['definition'] ?? null) ? $structured['definition'] : [],
                $profiles,
            );
        }

        $automation = $this->save($testCase, $definition, generated: true);
        $warnings = [];

        if (is_array($structured['warnings'] ?? null)) {
            foreach ($structured['warnings'] as $warning) {
                if (is_string($warning) && trim($warning) !== '') {
                    $warnings[] = trim($warning);
                }
            }
        }

        return ['automation' => $automation, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function generateStructured(array $context, string $prompt = 'Generate a safe browser automation definition for this test case.'): array
    {
        $response = AutomationDefinitionAgent::make(context: $context)->prompt($prompt);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The automation agent did not return structured output.');
        }

        return $response->structured;
    }

    /** @param array<string, mixed> $definition */
    public function save(TestCase $testCase, array $definition, bool $generated = false): TestCaseAutomation
    {
        $validated = $this->validateDefinition($testCase, $definition);

        return TestCaseAutomation::query()->updateOrCreate(
            ['test_case_id' => $testCase->id],
            [
                'driver' => TestCaseAutomation::DRIVER_PLAYWRIGHT_DSL,
                'schema_version' => 1,
                'status' => TestCaseAutomation::STATUS_DRAFT,
                'definition' => $validated,
                'source_hash' => $this->sourceHash($testCase),
                'generated_at' => $generated ? now() : $testCase->automation?->generated_at,
                'last_validated_environment' => null,
                'last_validated_at' => null,
                'validation_summary' => null,
                'approved_by' => null,
                'approval_source' => null,
                'approved_at' => null,
            ],
        )->fresh(['approver']);
    }

    public function approve(TestCaseAutomation $automation, User $user): TestCaseAutomation
    {
        $automation->loadMissing('testCase');

        if ($automation->source_hash !== $this->sourceHash($automation->testCase)) {
            throw ValidationException::withMessages([
                'automation' => 'The test case changed after this definition was created. Generate or save it again.',
            ]);
        }

        if ($automation->last_validated_at === null
            || $automation->last_validated_environment === TestRun::ENVIRONMENT_PRODUCTION
            || $automation->status !== TestCaseAutomation::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'automation' => 'A successful non-production validation is required before approval.',
            ]);
        }

        $automation->forceFill([
            'status' => TestCaseAutomation::STATUS_READY,
            'approved_by' => $user->id,
            'approval_source' => TestCaseAutomation::APPROVAL_USER,
            'approved_at' => now(),
        ])->save();

        return $automation->fresh(['approver']);
    }

    public function markValidationResult(
        TestCaseAutomation $automation,
        string $environment,
        bool $passed,
        ?string $summary,
    ): void {
        $automation->loadMissing('testCase');
        $isCurrent = $automation->source_hash === $this->sourceHash($automation->testCase);

        $automation->forceFill([
            'status' => $passed && $isCurrent
                ? TestCaseAutomation::STATUS_DRAFT
                : TestCaseAutomation::STATUS_INVALID,
            'last_validated_environment' => $passed && $isCurrent ? $environment : null,
            'last_validated_at' => $passed && $isCurrent ? now() : null,
            'validation_summary' => $summary,
            'approved_by' => null,
            'approval_source' => null,
            'approved_at' => null,
        ])->save();
    }

    public function invalidateIfChanged(TestCase $testCase): void
    {
        $automation = $testCase->automation;

        if (! $automation || $automation->source_hash === $this->sourceHash($testCase)) {
            return;
        }

        $automation->forceFill([
            'status' => TestCaseAutomation::STATUS_DRAFT,
            'last_validated_environment' => null,
            'last_validated_at' => null,
            'validation_summary' => 'The test case changed. Validate this definition again.',
            'approved_by' => null,
            'approval_source' => null,
            'approved_at' => null,
        ])->save();
    }

    public function isReady(TestCase $testCase): bool
    {
        $automation = $testCase->automation;

        return $automation !== null
            && $automation->status === TestCaseAutomation::STATUS_READY
            && $automation->approved_at !== null
            && $automation->source_hash === $this->sourceHash($testCase);
    }

    /** @return array{version: int, steps: list<array<string, mixed>>} */
    public function validateForEnvironment(TestCaseAutomation $automation, string $environment): array
    {
        $automation->loadMissing('testCase');

        return $this->validateDefinition(
            $automation->testCase,
            is_array($automation->definition) ? $automation->definition : [],
            $environment,
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{version: int, steps: list<array<string, mixed>>}
     */
    public function validateDefinition(TestCase $testCase, array $definition, ?string $environment = null): array
    {
        return $this->validator->validate(
            $this->stripNullValues($definition),
            $this->credentialProfiles($testCase, $environment),
        );
    }

    /** @return array<string, mixed> */
    public function compileStep(mixed $step, int $index): array
    {
        return $this->compiler->compileStep($step, $index);
    }

    public function sourceHash(TestCase $testCase): string
    {
        $testCase->loadMissing('steps');
        $payload = [
            'title' => $testCase->title,
            'preconditions' => $testCase->preconditions,
            'automation_status' => $testCase->automation_status,
            'steps' => $testCase->steps->map(fn ($step): array => [
                'position' => $step->position,
                'action' => $step->action,
                'expected_result' => $step->expected_result,
            ])->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, list<string>>  $credentialProfiles
     * @return array{version: int, steps: list<array<string, mixed>>}
     */
    private function compileAndValidate(array $definition, array $credentialProfiles): array
    {
        $compiled = $this->compiler->compileDefinition($definition);

        return $this->validator->validate($compiled, $credentialProfiles);
    }

    /** @return array<string, list<string>> */
    public function credentialProfiles(TestCase $testCase, ?string $environment = null): array
    {
        $query = ProjectCredentialProfile::query()->where('project_id', $testCase->project_id);

        if ($environment !== null) {
            $query->where('environment', $environment);
        }

        $profiles = [];

        foreach ($query->get() as $profile) {
            foreach ($profile->fields as $field) {
                if (! in_array($field, $profiles[$profile->key] ?? [], true)) {
                    $profiles[$profile->key][] = $field;
                }
            }
        }

        return $profiles;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stripNullValues(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[$key] = is_array($value) && ! array_is_list($value)
                ? $this->stripNullValues($value)
                : (is_array($value)
                    ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->stripNullValues($item) : $item, $value)
                    : $value);
        }

        return $result;
    }
}
