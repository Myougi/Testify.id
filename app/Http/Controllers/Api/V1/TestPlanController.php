<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TestPlan;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\TestPlanService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestPlanController extends Controller
{
    public function __construct(
        AuditService $audit,
        IdempotencyService $idempotency,
        private readonly TestPlanService $testPlanService,
    ) {
        parent::__construct($audit, $idempotency);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TestPlan::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'));

        foreach (['project' => 'project_id', 'status' => 'status', 'priority' => 'priority'] as $filter => $column) {
            if ($value = $request->input("filter.{$filter}")) {
                $query->where($column, $value);
            }
        }

        if ($search = trim((string) $request->input('filter.search'))) {
            $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        $sort = (string) $request->input('sort', '-created_at');
        $column = in_array(ltrim($sort, '-'), ['name', 'status', 'priority', 'due_date', 'created_at'], true)
            ? ltrim($sort, '-')
            : 'created_at';
        $plans = $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id')
            ->cursorPaginate(JsonApi::pageSize($request), cursor: $request->input('page.cursor'));

        return JsonApi::collection($plans, $plans->getCollection()->map(
            fn (TestPlan $plan): array => ResourceSerializer::serialize($plan),
        )->all());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request): JsonResponse {
            $plan = $this->testPlanService->store($this->validated($request));
            $this->audit->record($request, 'test_plan.created', 'test-plans', $plan, after: $plan->toArray());

            return $this->showResource($plan, 201);
        });
    }

    public function show(Request $request, string $testPlan): JsonResponse
    {
        return $this->showResource($this->testPlanService->getDetail($this->find($request, $testPlan)));
    }

    public function update(Request $request, string $testPlan): JsonResponse
    {
        $plan = $this->find($request, $testPlan);

        if ($response = $this->precondition($request, $plan)) {
            return $response;
        }

        $before = $plan->toArray();
        $updated = $this->testPlanService->update($plan, $this->validated($request, true, $plan));
        $this->audit->record($request, 'test_plan.updated', 'test-plans', $updated, $before, $updated->toArray());

        return $this->showResource($updated);
    }

    public function destroy(Request $request, string $testPlan): JsonResponse
    {
        $plan = $this->find($request, $testPlan);

        if ($response = $this->precondition($request, $plan)) {
            return $response;
        }

        if ($plan->testRuns()->exists()) {
            return JsonApi::error($request, 409, 'resource_has_children', 'Resource Has Children', 'Delete this plan’s test runs before deleting the plan.');
        }

        $before = $plan->toArray();
        $plan->delete();
        $this->audit->record($request, 'test_plan.deleted', 'test-plans', $plan, $before);

        return JsonApi::noContent();
    }

    private function find(Request $request, string $id): TestPlan
    {
        return TestPlan::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'))
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false, ?TestPlan $plan = null): array
    {
        $attributes = JsonApi::attributes($request, 'test-plans');
        $projectId = (int) data_get(JsonApi::relationship($request, 'project'), 'id', $plan?->project_id);
        $presence = $partial ? 'sometimes' : 'required';
        $data = validator([...$attributes, 'project_id' => $projectId], [
            'project_id' => [
                $presence,
                'integer',
                Rule::exists('projects', 'project_id')->whereNull('deleted_at'),
            ],
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'scope' => ['sometimes', 'nullable', 'string'],
            'strategy' => ['sometimes', 'nullable', 'string'],
            'criteria' => ['sometimes', 'nullable', 'string'],
            'status' => [$presence, 'string', Rule::in(TestPlan::statuses())],
            'priority' => [$presence, 'string', Rule::in(TestPlan::priorities())],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'release_version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tester' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        if ($partial && ! $request->has('data.relationships.project')) {
            unset($data['project_id']);
        }

        return $data;
    }
}
