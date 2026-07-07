<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestRun;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\IssueService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IssueController extends Controller
{
    public function __construct(
        AuditService $audit,
        IdempotencyService $idempotency,
        private readonly IssueService $issueService,
    ) {
        parent::__construct($audit, $idempotency);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Issue::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'));

        foreach ([
            'project' => 'project_id',
            'test_plan' => 'test_plan_id',
            'test_run' => 'test_run_id',
            'priority' => 'priority',
            'severity' => 'severity',
            'status' => 'status',
        ] as $filter => $column) {
            if ($value = $request->input("filter.{$filter}")) {
                $query->where($column, $value);
            }
        }

        if ($search = trim((string) $request->input('filter.search'))) {
            $query->where(fn ($inner) => $inner->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        $issues = $query->orderByDesc('created_at')->orderBy('id')
            ->cursorPaginate(JsonApi::pageSize($request), cursor: $request->input('page.cursor'));

        return JsonApi::collection($issues, $issues->getCollection()->map(
            fn (Issue $issue): array => ResourceSerializer::serialize($issue),
        )->all());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request): JsonResponse {
            [$project, $data] = $this->validated($request);
            $issue = $this->issueService->store($project, $data);
            $this->audit->record($request, 'issue.created', 'issues', $issue, after: $issue->toArray());

            return $this->showResource($issue, 201);
        });
    }

    public function show(Request $request, string $issue): JsonResponse
    {
        return $this->showResource($this->issueService->getDetail($this->find($request, $issue)));
    }

    public function update(Request $request, string $issue): JsonResponse
    {
        $model = $this->find($request, $issue);

        if ($response = $this->precondition($request, $model)) {
            return $response;
        }

        [, $changes] = $this->validated($request, true, $model);
        $data = [
            ...$model->only([
                'test_plan_id', 'test_run_id', 'title', 'description', 'expected_result', 'actual_result',
                'priority', 'severity', 'status', 'reporter', 'assignee', 'environment', 'defect_type',
            ]),
            ...$changes,
        ];
        $before = $model->toArray();
        $updated = $this->issueService->update($model, $data);
        $this->audit->record($request, 'issue.updated', 'issues', $updated, $before, $updated->toArray());

        return $this->showResource($updated);
    }

    public function destroy(Request $request, string $issue): JsonResponse
    {
        $model = $this->find($request, $issue);

        if ($response = $this->precondition($request, $model)) {
            return $response;
        }

        $before = $model->toArray();
        $model->delete();
        $this->audit->record($request, 'issue.deleted', 'issues', $model, $before);

        return JsonApi::noContent();
    }

    private function find(Request $request, string $id): Issue
    {
        return Issue::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'))
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array{0: Project, 1: array<string, mixed>}
     */
    private function validated(Request $request, bool $partial = false, ?Issue $issue = null): array
    {
        $attributes = JsonApi::attributes($request, 'issues');
        $projectId = (int) data_get(JsonApi::relationship($request, 'project'), 'id', $issue?->project_id);
        $planId = data_get(JsonApi::relationship($request, 'test_plan'), 'id', $issue?->test_plan_id);
        $runId = data_get(JsonApi::relationship($request, 'test_run'), 'id', $issue?->test_run_id);
        $project = $request->user()->accessibleProjects()->whereKey($projectId)->firstOrFail();
        $presence = $partial ? 'sometimes' : 'required';
        $data = validator([
            ...$attributes,
            'test_plan_id' => $planId,
            'test_run_id' => $runId,
        ], [
            'test_plan_id' => ['nullable', 'integer', Rule::exists('test_plans', 'id')->where('project_id', $projectId)->whereNull('deleted_at')],
            'test_run_id' => ['nullable', 'integer'],
            'title' => [$presence, 'string', 'max:255'],
            'description' => [$presence, 'string'],
            'expected_result' => ['sometimes', 'nullable', 'string'],
            'actual_result' => ['sometimes', 'nullable', 'string'],
            'priority' => [$presence, 'string', Rule::in(Issue::priorities())],
            'severity' => [$presence, 'string', Rule::in(Issue::severities())],
            'status' => [$presence, 'string', Rule::in(Issue::statuses())],
            'reporter' => [$presence, 'string', 'max:255'],
            'assignee' => ['sometimes', 'nullable', 'string', 'max:255'],
            'environment' => ['sometimes', 'nullable', 'string', Rule::in(Issue::environments())],
            'defect_type' => ['sometimes', 'nullable', 'string', Rule::in(Issue::defectTypes())],
        ])->validate();

        if ($runId !== null) {
            $run = TestRun::query()->with('testPlan')->whereKey($runId)->first();

            if (! $run || $run->testPlan?->project_id !== $projectId || ($planId !== null && $run->test_plan_id !== (int) $planId)) {
                throw ValidationException::withMessages(['test_run' => 'The test run must belong to the selected project and test plan.']);
            }
        }

        if ($partial) {
            if (! $request->has('data.relationships.test_plan')) {
                unset($data['test_plan_id']);
            }

            if (! $request->has('data.relationships.test_run')) {
                unset($data['test_run_id']);
            }
        }

        return [$project, $data];
    }
}
