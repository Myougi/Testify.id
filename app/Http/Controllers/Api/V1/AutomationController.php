<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationExecution;
use App\Models\AutomationHealingAttempt;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestCaseAutomation;
use App\Models\TestRun;
use App\Services\AiGenerationService;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationDispatchService;
use App\Services\Automation\AutomationSettingsService;
use App\Support\Api\V1\JsonApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationDefinitionService $definitions,
        private readonly AutomationDispatchService $dispatch,
        private readonly AutomationSettingsService $settings,
        private readonly AiGenerationService $ai,
        private readonly IdempotencyService $idempotency,
        private readonly AuditService $audit,
    ) {}

    public function settings(Request $request, string $project): JsonResponse
    {
        $model = $this->project($request, $project);

        return JsonApi::resource([
            'type' => 'project-automation-settings',
            'id' => (string) $model->project_id,
            'attributes' => $this->settings->payload($model),
            'meta' => ['etag' => JsonApi::etag($model)],
        ], etag: JsonApi::etag($model));
    }

    public function updateSettings(Request $request, string $project): JsonResponse
    {
        $model = $this->project($request, $project);

        if ($response = JsonApi::requireMatchingEtag($request, $model)) {
            return $response;
        }

        $attributes = JsonApi::attributes($request, 'project-automation-settings');
        $validated = validator($attributes, [
            'environments' => ['required', 'array', 'size:5'],
            'environments.*.environment' => ['required', 'string', 'distinct', Rule::in(TestRun::environments())],
            'environments.*.base_url' => ['nullable', 'url:http,https', 'max:2048'],
            'environments.*.enabled' => ['required', 'boolean'],
            'credential_profiles' => ['present', 'array', 'max:50'],
            'credential_profiles.*.environment' => ['required', 'string', Rule::in(TestRun::environments())],
            'credential_profiles.*.name' => ['required', 'string', 'max:255'],
            'credential_profiles.*.key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'credential_profiles.*.fields' => ['required', 'array', 'min:1', 'max:20'],
            'credential_profiles.*.fields.*' => ['required', 'string', 'distinct', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
        ])->validate();
        $payload = $this->settings->update($model, $validated['environments'], $validated['credential_profiles']);
        $model->touch();
        $this->audit->record($request, 'automation.settings_updated', 'projects', $model);

        return JsonApi::resource([
            'type' => 'project-automation-settings',
            'id' => (string) $model->project_id,
            'attributes' => $payload,
            'meta' => ['etag' => JsonApi::etag($model->fresh())],
        ], etag: JsonApi::etag($model->fresh()));
    }

    public function show(Request $request, string $testCase): JsonResponse
    {
        return $this->resource($this->testCase($request, $testCase)->automation()->firstOrFail());
    }

    public function healingHistory(Request $request, string $testCase): JsonResponse
    {
        $automation = $this->testCase($request, $testCase)->automation()->firstOrFail();
        $attempts = $automation->healingAttempts()
            ->latest()
            ->cursorPaginate(JsonApi::pageSize($request));

        return JsonApi::collection($attempts, $attempts->map(fn (AutomationHealingAttempt $attempt): array => [
            'type' => 'automation-healing-attempts',
            'id' => $attempt->id,
            'attributes' => $this->healingAttributes($attempt),
            'relationships' => [
                'automation' => ['data' => ['type' => 'test-case-automations', 'id' => (string) $automation->id]],
                'source_execution' => ['data' => ['type' => 'automation-executions', 'id' => $attempt->source_execution_id]],
                'revalidation_run' => ['data' => $attempt->revalidation_run_id
                    ? ['type' => 'automation-runs', 'id' => $attempt->revalidation_run_id]
                    : null],
            ],
        ])->all());
    }

    public function generate(Request $request, string $testCase): JsonResponse
    {
        JsonApi::assertType($request, 'automation-generation-requests');
        $case = $this->testCase($request, $testCase);

        return $this->idempotency->execute($request, function () use ($request, $case): JsonResponse {
            $result = $this->definitions->generate($case);
            $this->audit->record($request, 'automation.generated', 'test-case-automations', $result['automation'], after: [
                'warnings' => $result['warnings'],
            ]);

            return $this->resource($result['automation'], 201, ['warnings' => $result['warnings']]);
        }, $request->user());
    }

    public function update(Request $request, string $testCase): JsonResponse
    {
        $case = $this->testCase($request, $testCase);
        $current = $case->automation;

        if ($current && ($response = JsonApi::requireMatchingEtag($request, $current))) {
            return $response;
        }

        $attributes = JsonApi::attributes($request, 'test-case-automations');
        $validated = validator($attributes, ['definition' => ['required', 'array']])->validate();
        $automation = $this->definitions->save($case, $validated['definition']);
        $this->audit->record($request, 'automation.updated', 'test-case-automations', $automation);

        return $this->resource($automation, $current ? 200 : 201);
    }

    public function validateDefinition(Request $request, string $testCase): JsonResponse
    {
        $attributes = JsonApi::attributes($request, 'automation-validation-requests');
        $environment = validator($attributes, [
            'environment' => ['required', 'string', Rule::in(TestRun::environments())],
        ])->validate()['environment'];
        $case = $this->testCase($request, $testCase);

        return $this->idempotency->execute($request, function () use ($request, $case, $environment): JsonResponse {
            $run = $this->dispatch->dispatchValidation($case->automation()->firstOrFail(), $environment, $request->user());
            $this->audit->record($request, 'automation.validation_dispatched', 'automation-runs', $run);

            return JsonApi::resource([
                'type' => 'automation-runs',
                'id' => $run->id,
                'attributes' => ['kind' => $run->kind, 'status' => $run->status, 'environment' => $run->environment],
            ], 202);
        }, $request->user());
    }

    public function approve(Request $request, string $testCase): JsonResponse
    {
        JsonApi::assertType($request, 'automation-approval-requests');
        $automation = $this->testCase($request, $testCase)->automation()->firstOrFail();

        if ($response = JsonApi::requireMatchingEtag($request, $automation)) {
            return $response;
        }

        $updated = $this->definitions->approve($automation, $request->user());
        $this->audit->record($request, 'automation.approved', 'test-case-automations', $updated);

        return $this->resource($updated);
    }

    public function issueDraft(Request $request, string $execution): JsonResponse
    {
        JsonApi::assertType($request, 'automation-issue-draft-requests');
        $model = $this->execution($request, $execution);

        return $this->idempotency->execute($request, function () use ($request, $model): JsonResponse {
            $testRun = $model->automationRun->testRun;
            $project = $testRun !== null ? $testRun->testPlan->project : $model->testCase->project;
            $prompt = implode("\n", array_filter([
                "Create one issue draft for failed automated case {$model->testCase->case_key}: {$model->testCase->title}.",
                "Environment: {$model->automationRun->environment}.",
                "URL: {$model->automationRun->base_url}.",
                $model->failed_step ? "Failed automation step: {$model->failed_step}." : null,
                $model->failure_message ? "Failure: {$model->failure_message}" : null,
                $model->console_excerpt ? "Console excerpt: {$model->console_excerpt}" : null,
                'Use the evidence only. Do not claim details that are not present.',
            ]));
            $draft = $this->ai->generate($request->user(), [
                'artifact_type' => 'issues',
                'prompt' => $prompt,
                'project_id' => $project->project_id,
                'test_plan_id' => $testRun?->test_plan_id,
                'test_run_id' => $testRun?->id,
                'count' => 1,
            ], $project);
            $this->audit->record($request, 'automation.issue_draft_generated', 'automation-executions', $model);

            return JsonApi::resource([
                'type' => 'automation-issue-drafts',
                'id' => $model->id,
                'attributes' => ['draft' => $draft],
            ], 201);
        }, $request->user());
    }

    private function project(Request $request, string $id): Project
    {
        return $request->user()->projects()->whereKey($id)->firstOrFail();
    }

    private function execution(Request $request, string $id): AutomationExecution
    {
        return AutomationExecution::query()
            ->whereHas('testCase.project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->with(['automationRun.testRun.testPlan.project', 'testCase.project'])
            ->findOrFail($id);
    }

    private function testCase(Request $request, string $id): TestCase
    {
        return TestCase::query()
            ->whereHas('project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->with(['automation', 'steps'])
            ->findOrFail($id);
    }

    /** @param array<string, mixed> $meta */
    private function resource(TestCaseAutomation $automation, int $status = 200, array $meta = []): JsonResponse
    {
        return JsonApi::resource([
            'type' => 'test-case-automations',
            'id' => (string) $automation->id,
            'attributes' => [
                'driver' => $automation->driver,
                'schema_version' => $automation->schema_version,
                'status' => $automation->status,
                'definition' => $automation->definition,
                'source_hash' => $automation->source_hash,
                'generated_at' => $this->dateTime($automation->generated_at),
                'last_validated_environment' => $automation->last_validated_environment,
                'last_validated_at' => $this->dateTime($automation->last_validated_at),
                'validation_summary' => $automation->validation_summary,
                'approval_source' => $automation->approval_source,
                'approved_at' => $this->dateTime($automation->approved_at),
                'last_healed_at' => $this->dateTime($automation->last_healed_at),
            ],
            'relationships' => [
                'test_case' => ['data' => ['type' => 'test-cases', 'id' => (string) $automation->test_case_id]],
            ],
            'meta' => ['etag' => JsonApi::etag($automation)],
        ], $status, meta: $meta, etag: JsonApi::etag($automation));
    }

    /** @return array<string, mixed> */
    private function healingAttributes(AutomationHealingAttempt $attempt): array
    {
        return [
            'status' => $attempt->status,
            'failed_step' => $attempt->failed_step,
            'original_definition' => $attempt->original_definition,
            'proposed_definition' => $attempt->proposed_definition,
            'rationale' => $attempt->rationale,
            'confidence' => $attempt->confidence,
            'failure_message' => $attempt->failure_message,
            'created_at' => $this->dateTime($attempt->created_at),
            'completed_at' => $this->dateTime($attempt->completed_at),
        ];
    }

    private function dateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->utc()->toIso8601String();
    }
}
