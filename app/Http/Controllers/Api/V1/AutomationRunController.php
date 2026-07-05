<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\TestRun;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\Automation\AutomationDispatchService;
use App\Support\Api\V1\JsonApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationRunController extends Controller
{
    public function __construct(
        private readonly AutomationDispatchService $dispatch,
        private readonly IdempotencyService $idempotency,
        private readonly AuditService $audit,
    ) {}

    public function show(Request $request, string $automationRun): JsonResponse
    {
        return $this->resource($this->run($request, $automationRun));
    }

    public function index(Request $request, string $testRun): JsonResponse
    {
        $run = TestRun::query()
            ->whereHas('testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->findOrFail($testRun);
        $resources = $run->automationRuns()
            ->with(['executions.testCase', 'executions.artifacts'])
            ->latest('attempt')
            ->get()
            ->map(fn (AutomationRun $automationRun): array => $this->resourceData($automationRun))
            ->all();

        return response()->json([
            'jsonapi' => ['version' => '1.1'],
            'data' => $resources,
        ], headers: ['Content-Type' => JsonApi::MEDIA_TYPE]);
    }

    public function retry(Request $request, string $testRun): JsonResponse
    {
        JsonApi::assertType($request, 'automation-retry-requests');
        $run = TestRun::query()
            ->whereHas('testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->findOrFail($testRun);

        return $this->idempotency->execute($request, function () use ($request, $run): JsonResponse {
            $automationRun = $this->dispatch->retryFailed($run, $request->user());
            $this->audit->record($request, 'automation.retry_dispatched', 'automation-runs', $automationRun);

            return $this->resource($automationRun, 202);
        }, $request->user());
    }

    private function run(Request $request, string $id): AutomationRun
    {
        return AutomationRun::query()
            ->where(function ($query) use ($request): void {
                $query->whereHas('testRun.testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id))
                    ->orWhereHas('executions.testCase.project', fn ($project) => $project->where('user_id', $request->user()->id));
            })
            ->with(['executions.testCase', 'executions.artifacts'])
            ->findOrFail($id);
    }

    private function resource(AutomationRun $run, int $status = 200): JsonResponse
    {
        return JsonApi::resource($this->resourceData($run), $status);
    }

    /** @return array<string, mixed> */
    private function resourceData(AutomationRun $run): array
    {
        $run->loadMissing(['executions.testCase', 'executions.artifacts']);

        return [
            'type' => 'automation-runs',
            'id' => $run->id,
            'attributes' => [
                'kind' => $run->kind,
                'attempt' => $run->attempt,
                'environment' => $run->environment,
                'status' => $run->status,
                'provider_run_url' => $run->provider_run_url,
                'phase' => $run->phase,
                'progress_message' => $run->progress_message,
                'generation_source' => $run->generation_source,
                'warnings' => $run->warnings ?? [],
                'failure_code' => $run->failure_code,
                'failure_message' => $run->failure_message,
                'started_at' => $run->started_at?->utc()->toIso8601String(),
                'completed_at' => $run->completed_at?->utc()->toIso8601String(),
                'executions' => $run->executions->map(fn (AutomationExecution $execution): array => [
                    'id' => $execution->id,
                    'test_case_id' => (string) $execution->test_case_id,
                    'case_key' => $execution->testCase->case_key,
                    'status' => $execution->status,
                    'duration_ms' => $execution->duration_ms,
                    'failure_type' => $execution->failure_type,
                    'failure_message' => $execution->failure_message,
                    'failed_step' => $execution->failed_step,
                ])->all(),
            ],
        ];
    }
}
