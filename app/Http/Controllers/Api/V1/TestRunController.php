<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TestPlan;
use App\Models\TestRun;
use App\Services\TestRunService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestRunController extends Controller
{
    public function __construct(
        \App\Services\Api\V1\AuditService $audit,
        \App\Services\Api\V1\IdempotencyService $idempotency,
        private readonly TestRunService $testRunService,
    ) {
        parent::__construct($audit, $idempotency);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TestRun::query()
            ->with($request->string('include')->contains('test_cases') ? ['testCases'] : [])
            ->whereHas('testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id));

        foreach (['test_plan' => 'test_plan_id', 'status' => 'status', 'priority' => 'priority', 'environment' => 'environment'] as $filter => $column) {
            if ($value = $request->input("filter.{$filter}")) {
                $query->where($column, $value);
            }
        }

        $runs = $query->orderByDesc('created_at')->orderBy('id')
            ->cursorPaginate(JsonApi::pageSize($request), cursor: $request->input('page.cursor'));

        return JsonApi::collection($runs, $runs->getCollection()->map(
            fn (TestRun $run): array => ResourceSerializer::serialize($run),
        )->all());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request): JsonResponse {
            [$data, $caseIds] = $this->validated($request);
            $run = $this->testRunService->store($data, $caseIds);
            $this->audit->record($request, 'test_run.created', 'test-runs', $run, after: $run->toArray());

            return $this->showResource($run, 201);
        });
    }

    public function show(Request $request, string $testRun): JsonResponse
    {
        return $this->showResource($this->testRunService->getDetail($this->find($request, $testRun)));
    }

    public function update(Request $request, string $testRun): JsonResponse
    {
        $run = $this->find($request, $testRun);

        if ($response = $this->precondition($request, $run)) {
            return $response;
        }

        [$data, $caseIds] = $this->validated($request, true, $run);
        $before = $run->load('testCases')->toArray();
        $updated = $this->testRunService->update($run, $data, $caseIds);
        $this->audit->record($request, 'test_run.updated', 'test-runs', $updated, $before, $updated->toArray());

        return $this->showResource($updated);
    }

    public function destroy(Request $request, string $testRun): JsonResponse
    {
        $run = $this->find($request, $testRun);

        if ($response = $this->precondition($request, $run)) {
            return $response;
        }

        if ($run->issues()->exists()) {
            return JsonApi::error($request, 409, 'resource_has_children', 'Resource Has Children', 'Delete or unlink this run’s issues before deleting it.');
        }

        $before = $run->load('testCases')->toArray();
        $run->delete();
        $this->audit->record($request, 'test_run.deleted', 'test-runs', $run, $before);

        return JsonApi::noContent();
    }

    public function start(Request $request, string $testRun): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request, $testRun): JsonResponse {
            $run = $this->find($request, $testRun);

            if ($response = $this->precondition($request, $run)) {
                return $response;
            }

            $before = $run->toArray();
            $updated = $this->testRunService->start($run);
            $this->audit->record($request, 'test_run.started', 'test-runs', $updated, $before, $updated->toArray());

            return $this->showResource($updated);
        });
    }

    public function updateCaseResult(Request $request, string $testRun, string $testCase): JsonResponse
    {
        $run = $this->find($request, $testRun);

        if ($response = $this->precondition($request, $run)) {
            return $response;
        }

        JsonApi::assertType($request, 'test-run-case-results');
        $status = validator(JsonApi::attributes($request, 'test-run-case-results'), [
            'execution_status' => ['required', 'string', Rule::in(TestRun::caseStatuses())],
        ])->validate()['execution_status'];
        $case = $run->testCases()->whereKey($testCase)->firstOrFail();
        $updated = $this->testRunService->updateCaseStatus($run, $case, $status);
        $this->audit->record($request, 'test_run.case_result_updated', 'test-runs', $run, after: [
            'test_case_id' => $case->id,
            'execution_status' => $status,
        ]);

        return $this->showResource($updated);
    }

    private function find(Request $request, string $id): TestRun
    {
        return TestRun::query()
            ->whereHas('testPlan.project', fn ($project) => $project->where('user_id', $request->user()->id))
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function validated(Request $request, bool $partial = false, ?TestRun $run = null): array
    {
        $attributes = JsonApi::attributes($request, 'test-runs');
        unset($attributes['status'], $attributes['execution_started_at'], $attributes['execution_completed_at']);
        $planId = (int) data_get(JsonApi::relationship($request, 'test_plan'), 'id', $run?->test_plan_id);
        $presence = $partial ? 'sometimes' : 'required';
        $data = validator([...$attributes, 'test_plan_id' => $planId], [
            'test_plan_id' => [
                $presence,
                'integer',
                Rule::exists('test_plans', 'id')->whereIn(
                    'project_id',
                    $request->user()->projects()->pluck('project_id')->all(),
                )->whereNull('deleted_at'),
            ],
            'name' => [$presence, 'string', 'max:255'],
            'environment' => [$presence, 'string', Rule::in(TestRun::environments())],
            'priority' => [$presence, 'string', Rule::in(TestRun::priorities())],
            'description' => ['sometimes', 'nullable', 'string'],
            'tester_name' => [$presence, 'string', 'max:255'],
        ])->validate();

        if ($partial && ! $request->has('data.relationships.test_plan')) {
            unset($data['test_plan_id']);
        }

        $effectivePlanId = $data['test_plan_id'] ?? $run?->test_plan_id;
        $caseIds = $request->has('data.relationships.test_cases')
            ? collect(JsonApi::relationship($request, 'test_cases'))->map(fn (array $linkage): int => (int) ($linkage['id'] ?? 0))->all()
            : ($run?->testCases()->pluck('test_cases.id')->map(fn (mixed $id): int => (int) $id)->all() ?? []);
        $plan = TestPlan::query()->whereKey($effectivePlanId)->firstOrFail();
        $validCount = $plan->testCases()->whereIn('test_cases.id', $caseIds)->count();

        if ($caseIds === [] || $validCount !== count(array_unique($caseIds))) {
            throw ValidationException::withMessages(['test_cases' => 'Every selected test case must belong to the selected test plan.']);
        }

        return [$data, array_values(array_unique($caseIds))];
    }
}
