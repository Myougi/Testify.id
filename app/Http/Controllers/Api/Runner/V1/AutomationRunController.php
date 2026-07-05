<?php

namespace App\Http\Controllers\Api\Runner\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\User;
use App\Services\Api\V1\AuditService;
use App\Services\Automation\AutomationGenerationService;
use App\Services\Automation\AutomationResultService;
use App\Services\Automation\RunnerIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationRunController extends Controller
{
    public function __construct(
        private readonly AutomationResultService $results,
        private readonly AutomationGenerationService $generation,
        private readonly AuditService $audit,
        private readonly RunnerIdempotencyService $idempotency,
    ) {}

    public function show(Request $request, string $automationRun): JsonResponse
    {
        $run = $this->run($automationRun);
        $this->audit->record($request, 'automation_run.claimed', 'automation-runs', $run, actor: $run->requester);

        return response()->json(['data' => $this->results->payload($run)]);
    }

    public function start(Request $request, string $automationRun): JsonResponse
    {
        $validated = $request->validate([
            'provider_run_id' => ['nullable', 'string', 'max:255'],
            'provider_run_url' => ['nullable', 'url:https', 'max:2048'],
        ]);
        $run = $this->run($automationRun);

        $actor = $this->actor($run);

        return $this->idempotency->execute($request, $actor, function () use ($request, $run, $validated, $actor): JsonResponse {
            $started = $this->results->start(
                $run,
                $validated['provider_run_id'] ?? null,
                $validated['provider_run_url'] ?? null,
            );
            $this->audit->record($request, 'automation_run.started', 'automation-runs', $started, actor: $actor);

            return response()->json(['data' => ['id' => $started->id, 'status' => $started->status]]);
        });
    }

    public function result(Request $request, string $automationRun, string $execution): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                AutomationExecution::STATUS_PASSED,
                AutomationExecution::STATUS_FAILED,
                AutomationExecution::STATUS_BLOCKED,
                AutomationExecution::STATUS_SKIPPED,
            ])],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'failure_type' => ['nullable', 'string', 'max:40'],
            'failure_code' => ['nullable', 'string', Rule::in([
                'locator_not_found',
                'locator_ambiguous',
                'assertion_failed',
                'navigation_failed',
                'step_timeout',
                'credential_error',
                'infrastructure_error',
            ])],
            'failure_message' => ['nullable', 'string', 'max:10000'],
            'failed_step' => ['nullable', 'integer', 'min:1', 'max:100'],
            'console_excerpt' => ['nullable', 'string', 'max:12000'],
        ]);
        $run = $this->run($automationRun);
        $model = $run->executions()->whereKey($execution)->firstOrFail();

        $actor = $this->actor($run);

        return $this->idempotency->execute($request, $actor, function () use ($request, $model, $validated, $actor): JsonResponse {
            $updated = $this->results->completeExecution($model, $validated);
            $this->audit->record($request, 'automation_execution.completed', 'automation-executions', $updated, after: [
                'status' => $updated->status,
                'duration_ms' => $updated->duration_ms,
                'failure_type' => $updated->failure_type,
            ], actor: $actor);

            return response()->json(['data' => ['id' => $updated->id, 'status' => $updated->status]]);
        });
    }

    public function progress(Request $request, string $automationRun): JsonResponse
    {
        $validated = $request->validate([
            'phase' => ['required', 'string', Rule::in([
                AutomationRun::PHASE_STARTING_BROWSER,
                AutomationRun::PHASE_EXPLORING,
                AutomationRun::PHASE_COMPILING,
            ])],
            'message' => ['nullable', 'string', 'max:500'],
        ]);
        $run = $this->run($automationRun);
        $actor = $this->actor($run);

        return $this->idempotency->execute($request, $actor, function () use ($request, $run, $validated, $actor): JsonResponse {
            $updated = $this->generation->progress($run, $validated['phase'], $validated['message'] ?? null);
            $this->audit->record($request, 'automation_generation.progressed', 'automation-runs', $updated, after: [
                'phase' => $updated->phase,
                'progress_message' => $updated->progress_message,
            ], actor: $actor);

            return response()->json(['data' => ['id' => $updated->id, 'phase' => $updated->phase]]);
        });
    }

    public function generationResult(Request $request, string $automationRun, string $execution): JsonResponse
    {
        $validated = $request->validate([
            'success' => ['required', 'boolean'],
            'definition' => ['nullable', 'array', 'required_if:success,true'],
            'warnings' => ['present', 'array', 'max:20'],
            'warnings.*' => ['string', 'max:255'],
            'failure_code' => ['nullable', 'string', 'max:60'],
            'failure_message' => ['nullable', 'string', 'max:10000'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
        ]);
        $run = $this->run($automationRun);
        $model = $run->executions()->whereKey($execution)->firstOrFail();
        $actor = $this->actor($run);

        return $this->idempotency->execute($request, $actor, function () use ($request, $model, $validated, $actor): JsonResponse {
            $updated = $this->generation->completeAgentic($model, $validated);
            $this->audit->record($request, 'automation_generation.completed', 'automation-executions', $updated, after: [
                'status' => $updated->status,
                'failure_code' => $updated->failure_code,
            ], actor: $actor);

            return response()->json(['data' => ['id' => $updated->id, 'status' => $updated->status]]);
        });
    }

    public function reserveArtifact(Request $request, string $automationRun, string $execution): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['Screenshot', 'Trace', 'Log', 'Diagnostic'])],
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1'],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $run = $this->run($automationRun);
        $model = $run->executions()->whereKey($execution)->firstOrFail();
        $actor = $this->actor($run);

        return $this->idempotency->execute($request, $actor, function () use ($request, $model, $validated, $actor): JsonResponse {
            $reserved = $this->results->reserveArtifact($model, $validated);
            $this->audit->record($request, 'automation_artifact.reserved', 'automation-artifacts', $reserved['artifact'], after: [
                'type' => $reserved['artifact']->type,
                'mime_type' => $reserved['artifact']->mime_type,
                'size' => $reserved['artifact']->size,
            ], actor: $actor);

            return response()->json([
                'data' => [
                    'id' => $reserved['artifact']->id,
                    'upload_url' => $reserved['upload_url'],
                    'headers' => $reserved['headers'],
                ],
            ], 201);
        });
    }

    public function finalizeArtifact(
        Request $request,
        string $automationRun,
        string $execution,
        string $artifact,
    ): JsonResponse {
        $run = $this->run($automationRun);
        $model = $run->executions()->whereKey($execution)->firstOrFail();
        $reserved = AutomationArtifact::query()
            ->where('automation_execution_id', $model->id)
            ->findOrFail($artifact);

        $actor = $this->actor($run);

        return $this->idempotency->execute($request, $actor, function () use ($request, $model, $reserved, $actor): JsonResponse {
            $finalized = $this->results->finalizeArtifact($model, $reserved);
            $this->audit->record($request, 'automation_artifact.finalized', 'automation-artifacts', $finalized, after: [
                'uploaded_at' => $finalized->uploaded_at?->toIso8601String(),
            ], actor: $actor);

            return response()->json(['data' => ['id' => $finalized->id, 'status' => 'Uploaded']]);
        });
    }

    private function run(string $id): AutomationRun
    {
        return AutomationRun::query()->with(['requester', 'executions.testCase'])->findOrFail($id);
    }

    private function actor(AutomationRun $run): User
    {
        return $run->requester()->firstOrFail();
    }
}
