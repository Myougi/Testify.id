<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TestCase;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\TestRepositoryService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestCaseController extends Controller
{
    public function __construct(
        AuditService $audit,
        IdempotencyService $idempotency,
        private readonly TestRepositoryService $repositoryService,
    ) {
        parent::__construct($audit, $idempotency);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TestCase::query()
            ->with($request->string('include')->contains('steps') ? ['steps'] : [])
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'));

        foreach ([
            'project' => 'project_id',
            'module' => 'module_id',
            'priority' => 'priority',
            'automation_status' => 'automation_status',
            'test_behavior' => 'test_behavior',
            'test_type' => 'test_type',
        ] as $filter => $column) {
            if ($value = $request->input("filter.{$filter}")) {
                $query->where($column, $value);
            }
        }

        if ($search = trim((string) $request->input('filter.search'))) {
            $query->where(fn ($inner) => $inner->where('case_key', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('preconditions', 'like', "%{$search}%"));
        }

        $sort = (string) $request->input('sort', 'case_key');
        $column = in_array(ltrim($sort, '-'), ['case_key', 'title', 'priority', 'created_at', 'updated_at'], true)
            ? ltrim($sort, '-')
            : 'case_key';
        $cases = $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id')
            ->cursorPaginate(JsonApi::pageSize($request), cursor: $request->input('page.cursor'));

        return JsonApi::collection($cases, $cases->getCollection()->map(
            fn (TestCase $testCase): array => ResourceSerializer::serialize($testCase),
        )->all());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request): JsonResponse {
            $testCase = $this->repositoryService->storeCase($this->validated($request));
            $this->audit->record($request, 'test_case.created', 'test-cases', $testCase, after: $testCase->toArray());

            return $this->showResource($testCase, 201);
        });
    }

    public function show(Request $request, string $testCase): JsonResponse
    {
        return $this->showResource($this->find($request, $testCase)->load('steps'));
    }

    public function update(Request $request, string $testCase): JsonResponse
    {
        $model = $this->find($request, $testCase);

        if ($response = $this->precondition($request, $model)) {
            return $response;
        }

        $before = $model->load('steps')->toArray();
        $updated = $this->repositoryService->updateCase($model, $this->validated($request, true, $model));
        $this->audit->record($request, 'test_case.updated', 'test-cases', $updated, $before, $updated->toArray());

        return $this->showResource($updated);
    }

    public function destroy(Request $request, string $testCase): JsonResponse
    {
        $model = $this->find($request, $testCase);

        if ($response = $this->precondition($request, $model)) {
            return $response;
        }

        if ($model->testPlans()->exists() || $model->testRuns()->exists()) {
            return JsonApi::error($request, 409, 'resource_has_relationships', 'Resource Has Relationships', 'Detach this test case from plans and runs before deleting it.');
        }

        $before = $model->load('steps')->toArray();
        $model->delete();
        $this->audit->record($request, 'test_case.deleted', 'test-cases', $model, $before);

        return JsonApi::noContent();
    }

    private function find(Request $request, string $id): TestCase
    {
        return TestCase::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'))
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false, ?TestCase $testCase = null): array
    {
        $attributes = JsonApi::attributes($request, 'test-cases');
        unset($attributes['case_key']);
        $projectId = (int) data_get(JsonApi::relationship($request, 'project'), 'id', $testCase?->project_id);
        $moduleId = (int) data_get(JsonApi::relationship($request, 'module'), 'id', $testCase?->module_id);
        $presence = $partial ? 'sometimes' : 'required';
        $data = validator([...$attributes, 'project_id' => $projectId, 'module_id' => $moduleId], [
            'project_id' => [
                $presence,
                'integer',
                Rule::exists('projects', 'project_id')->whereNull('deleted_at'),
            ],
            'module_id' => [
                $presence,
                'integer',
                Rule::exists('test_repository_modules', 'id')->where('project_id', $projectId)->whereNull('deleted_at'),
            ],
            'title' => [$presence, 'string', 'max:255'],
            'priority' => [$presence, 'string', Rule::in(TestCase::priorities())],
            'automation_status' => [$presence, 'string', Rule::in(TestCase::automationStatuses())],
            'test_behavior' => [$presence, 'string', Rule::in(TestCase::behaviors())],
            'test_type' => [$presence, 'string', Rule::in(TestCase::types())],
            'preconditions' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        if ($partial && ! $request->has('data.relationships.project')) {
            unset($data['project_id']);
        }

        if ($partial && ! $request->has('data.relationships.module')) {
            unset($data['module_id']);
        }

        if (! $partial || $request->has('data.relationships.steps')) {
            $data['steps'] = $this->steps($request);
        }

        return $data;
    }

    /**
     * @return array<int, array{action: string, expected_result: string}>
     */
    private function steps(Request $request): array
    {
        $linkages = array_values(JsonApi::relationship($request, 'steps'));
        $includedInput = $request->input('included', []);
        $includedResources = is_array($includedInput) ? $includedInput : [];
        $included = [];

        foreach ($includedResources as $resource) {
            if (is_array($resource) && data_get($resource, 'type') === 'test-case-steps') {
                $included[(string) ($resource['lid'] ?? '')] = $resource;
            }
        }

        if ($linkages === []) {
            throw ValidationException::withMessages(['steps' => 'At least one test case step is required.']);
        }

        $steps = [];

        foreach ($linkages as $index => $linkage) {
            $lid = (string) data_get($linkage, 'lid');
            $resource = $included[$lid] ?? null;

            if ($lid === '' || ! is_array($resource)) {
                throw ValidationException::withMessages(["steps.{$index}" => 'Each step linkage must reference an included resource by lid.']);
            }

            $attributes = validator($resource['attributes'] ?? [], [
                'step_number' => ['required', 'integer', 'min:1'],
                'action' => ['required', 'string'],
                'expected_result' => ['required', 'string'],
            ])->validate();

            if ((int) $attributes['step_number'] !== $index + 1) {
                throw ValidationException::withMessages(["steps.{$index}.step_number" => 'Step numbers must be contiguous and match relationship order.']);
            }

            $steps[] = [
                'action' => $attributes['action'],
                'expected_result' => $attributes['expected_result'],
            ];
        }

        return $steps;
    }
}
