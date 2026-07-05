<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use App\Models\AutomationHealingAttempt;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Services\AiGenerationService;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationDispatchService;
use App\Services\Automation\AutomationGenerationService;
use App\Services\Automation\AutomationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AutomationController extends Controller
{
    public function __construct(
        private readonly AutomationDefinitionService $definitions,
        private readonly AutomationDispatchService $dispatch,
        private readonly AutomationGenerationService $generation,
        private readonly AutomationSettingsService $settings,
        private readonly AiGenerationService $ai,
    ) {}

    public function settings(Request $request, string $project): JsonResponse
    {
        return response()->json($this->settings->payload($this->project($request, $project)));
    }

    public function updateSettings(Request $request, string $project): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

        return response()->json($this->settings->update(
            $this->project($request, $project),
            $validated['environments'],
            $validated['credential_profiles'],
        ));
    }

    public function show(Request $request, string $testCase): JsonResponse
    {
        return response()->json(['data' => $this->automationPayload($this->testCase($request, $testCase))]);
    }

    public function healingHistory(Request $request, string $testCase): JsonResponse
    {
        $case = $this->testCase($request, $testCase);
        $attempts = $case->automation?->healingAttempts()
            ->with(['sourceExecution', 'revalidationRun'])
            ->latest()
            ->limit(50)
            ->get() ?? collect();

        return response()->json(['data' => $attempts->map(
            fn (AutomationHealingAttempt $attempt): array => $this->healingPayload($attempt),
        )->values()->all()]);
    }

    public function generate(Request $request, string $testCase): JsonResponse
    {
        $validated = $request->validate([
            'environment' => ['required', 'string', Rule::in(TestRun::environments())],
            'prompt' => ['nullable', 'string', 'max:'.config('automation.agentic_generation.prompt_max_characters', 1000)],
        ]);
        $run = $this->generation->dispatch(
            $this->testCase($request, $testCase),
            $validated['environment'],
            $request->user(),
            $validated['prompt'] ?? null,
        );

        return response()->json(['data' => $this->runPayload($run)], 202);
    }

    public function update(Request $request, string $testCase): JsonResponse
    {
        $validated = $request->validate(['definition' => ['required', 'array']]);
        $case = $this->testCase($request, $testCase);
        $this->definitions->save($case, $validated['definition']);

        return response()->json(['data' => $this->automationPayload($case->fresh())]);
    }

    public function validateDefinition(Request $request, string $testCase): JsonResponse
    {
        $validated = $request->validate([
            'environment' => ['required', 'string', Rule::in(TestRun::environments())],
        ]);
        $case = $this->testCase($request, $testCase)->load('automation');
        $run = $this->dispatch->dispatchValidation($case->automation()->firstOrFail(), $validated['environment'], $request->user());

        return response()->json(['data' => $this->runPayload($run)], 202);
    }

    public function approve(Request $request, string $testCase): JsonResponse
    {
        $case = $this->testCase($request, $testCase);
        $this->definitions->approve($case->automation()->firstOrFail(), $request->user());

        return response()->json(['data' => $this->automationPayload($case->fresh())]);
    }

    public function retry(Request $request, string $testRun): JsonResponse
    {
        $run = $this->dispatch->retryFailed($this->testRun($request, $testRun), $request->user());

        return response()->json(['data' => $this->runPayload($run)], 202);
    }

    public function runs(Request $request, string $testRun): JsonResponse
    {
        $runs = $this->testRun($request, $testRun)->automationRuns()
            ->with(['executions.testCase', 'executions.artifacts'])
            ->latest('attempt')
            ->get();

        return response()->json(['data' => $runs->map(fn (AutomationRun $run): array => $this->runPayload($run))->all()]);
    }

    public function issueDraft(Request $request, string $execution): JsonResponse
    {
        $model = $this->execution($request, $execution);
        $model->loadMissing(['automationRun.testRun.testPlan.project', 'testCase', 'artifacts']);
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
        $payload = $this->ai->generate($request->user(), [
            'artifact_type' => 'issues',
            'prompt' => $prompt,
            'project_id' => $project->project_id,
            'test_plan_id' => $testRun?->test_plan_id,
            'test_run_id' => $testRun?->id,
            'count' => 1,
        ], $project);

        return response()->json($payload);
    }

    public function artifact(Request $request, string $artifact): StreamedResponse
    {
        $model = AutomationArtifact::query()
            ->whereNotNull('uploaded_at')
            ->whereHas('execution.automationRun', function ($query) use ($request): void {
                $query->whereHas('testRun.testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id))
                    ->orWhereHas('executions.testCase.project', fn ($project) => $project->where('user_id', $request->user()->id));
            })
            ->findOrFail($artifact);

        return Storage::disk($model->disk)->download($model->path, $model->original_name);
    }

    private function project(Request $request, string $id): Project
    {
        return $request->user()->projects()->whereKey($id)->firstOrFail();
    }

    private function testCase(Request $request, string $id): TestCase
    {
        return TestCase::query()
            ->whereHas('project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->with(['automation.approver', 'project.credentialProfiles', 'steps'])
            ->findOrFail($id);
    }

    private function testRun(Request $request, string $id): TestRun
    {
        return TestRun::query()
            ->whereHas('testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->findOrFail($id);
    }

    private function execution(Request $request, string $id): AutomationExecution
    {
        return AutomationExecution::query()
            ->whereHas('testCase.project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function automationPayload(TestCase $testCase): array
    {
        $testCase->loadMissing([
            'automation.approver',
            'automation.healingAttempts.sourceExecution',
            'automation.healingAttempts.revalidationRun',
            'automation.generationRun',
            'project.environments',
            'project.credentialProfiles',
        ]);
        $automation = $testCase->automation;

        return [
            'test_case_id' => $testCase->id,
            'automation_status' => $testCase->automation_status,
            'automation' => $automation ? [
                'id' => $automation->id,
                'driver' => $automation->driver,
                'schema_version' => $automation->schema_version,
                'status' => $automation->status,
                'definition' => $automation->definition,
                'source_hash' => $automation->source_hash,
                'generated_at' => $this->dateTime($automation->generated_at),
                'last_validated_environment' => $automation->last_validated_environment,
                'last_validated_at' => $this->dateTime($automation->last_validated_at),
                'validation_summary' => $automation->validation_summary,
                'approved_by' => $automation->approver?->name,
                'approval_source' => $automation->approval_source,
                'approved_at' => $this->dateTime($automation->approved_at),
                'last_healed_at' => $this->dateTime($automation->last_healed_at),
                'generation_source' => $automation->generation_source,
                'generation_run_id' => $automation->generation_run_id,
                'healing_history' => $automation->healingAttempts
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->map(fn (AutomationHealingAttempt $attempt): array => $this->healingPayload($attempt))
                    ->values()->all(),
            ] : null,
            'latest_generation' => $this->latestGenerationPayload($testCase),
            'settings' => $this->settings->payload($testCase->project),
        ];
    }

    /** @return array<string, mixed> */
    private function runPayload(AutomationRun $run): array
    {
        $run->loadMissing(['executions.testCase', 'executions.artifacts']);

        return [
            'id' => $run->id,
            'kind' => $run->kind,
            'attempt' => $run->attempt,
            'environment' => $run->environment,
            'status' => $run->status,
            'provider_run_url' => $run->provider_run_url,
            'failure_message' => $run->failure_message,
            'failure_code' => $run->failure_code,
            'phase' => $run->phase,
            'progress_message' => $run->progress_message,
            'generation_source' => $run->generation_source,
            'warnings' => $run->warnings ?? [],
            'started_at' => $this->dateTime($run->started_at),
            'completed_at' => $this->dateTime($run->completed_at),
            'executions' => $run->executions->map(fn (AutomationExecution $execution): array => [
                'id' => $execution->id,
                'test_case_id' => $execution->test_case_id,
                'case_key' => $execution->testCase->case_key,
                'status' => $execution->status,
                'duration_ms' => $execution->duration_ms,
                'failure_type' => $execution->failure_type,
                'failure_code' => $execution->failure_code,
                'failure_message' => $execution->failure_message,
                'failed_step' => $execution->failed_step,
                'artifacts' => $execution->artifacts
                    ->whereNotNull('uploaded_at')
                    ->map(fn (AutomationArtifact $artifact): array => [
                        'id' => $artifact->id,
                        'type' => $artifact->type,
                        'name' => $artifact->original_name,
                    ])->values()->all(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestGenerationPayload(TestCase $testCase): ?array
    {
        $run = AutomationRun::query()
            ->where('kind', AutomationRun::KIND_GENERATION)
            ->whereHas('executions', fn ($query) => $query->where('test_case_id', $testCase->id))
            ->latest()
            ->first();

        return $run ? $this->runPayload($run) : null;
    }

    /** @return array<string, mixed> */
    private function healingPayload(AutomationHealingAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'failed_step' => $attempt->failed_step,
            'original_definition' => $attempt->original_definition,
            'proposed_definition' => $attempt->proposed_definition,
            'rationale' => $attempt->rationale,
            'confidence' => $attempt->confidence,
            'failure_message' => $attempt->failure_message,
            'source_execution_id' => $attempt->source_execution_id,
            'revalidation_run_id' => $attempt->revalidation_run_id,
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
