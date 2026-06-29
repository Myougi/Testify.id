<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkTestCasesRequest;
use App\Http\Requests\StoreTestCaseRequest;
use App\Http\Requests\UpdateTestCaseRequest;
use App\Http\Resources\TestCaseCollection;
use App\Http\Resources\TestCaseResource;
use App\Http\Resources\TestRepositoryModuleResource;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Services\TestRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TestCaseController extends Controller
{
    public function __construct(
        public TestRepositoryService $testRepositoryService,
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): TestCaseCollection
    {
        Gate::authorize('viewAny', TestCase::class);

        $filters = $this->validatedFilters($request);

        return (new TestCaseCollection(
            $this->testRepositoryService->paginateCasesForUser($request->user(), $filters),
        ))->additional([
            'stats' => $this->testRepositoryService->getStats($request->user()),
            'filters' => [
                ...$filters,
                'page' => (int) $request->integer('page', 1),
            ],
            'options' => [
                'projects' => $this->testRepositoryService->getProjectOptions($request->user()),
                'modules' => TestRepositoryModuleResource::collection(
                    $this->testRepositoryService->getModulesForUser($request->user()),
                )->resolve(),
                'priorities' => TestCase::priorities(),
                'automationStatuses' => TestCase::automationStatuses(),
                'behaviors' => TestCase::behaviors(),
                'types' => TestCase::types(),
                'aiGeneration' => $this->aiGenerationOptions($request),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTestCaseRequest $request): JsonResponse
    {
        return (new TestCaseResource(
            $this->testRepositoryService->storeCase($request->validated()),
        ))->response()->setStatusCode(201);
    }

    public function bulkStore(StoreBulkTestCasesRequest $request): JsonResponse
    {
        $project = $this->activeProject($request);
        abort_unless($project instanceof Project, 422, 'An active project is required.');

        $testPlan = $project->testPlans()
            ->whereKey($request->integer('test_plan_id'))
            ->first();
        abort_unless($testPlan instanceof TestPlan, 422, 'The selected test plan does not belong to the active project.');

        /** @var array<int, array<string, mixed>> $cases */
        $cases = $request->validated()['cases'];

        $createdCases = $this->testRepositoryService->bulkStoreCases(
            $project,
            $request->integer('module_id'),
            $cases,
        );

        $createdCases->load(['project', 'module', 'steps']);
        $module = $createdCases->first()?->module;

        return response()->json([
            'data' => TestCaseResource::collection($createdCases)->resolve(),
            'meta' => [
                'created' => $createdCases->count(),
                'test_plan' => [
                    'id' => $testPlan->id,
                    'name' => $testPlan->name,
                ],
                'module' => $module ? [
                    'id' => $module->id,
                    'name' => $module->name,
                ] : null,
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TestCase $testCase): TestCaseResource
    {
        Gate::authorize('view', $testCase);

        return new TestCaseResource($testCase->load(['project', 'module', 'steps']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTestCaseRequest $request, TestCase $testCase): TestCaseResource
    {
        return new TestCaseResource(
            $this->testRepositoryService->updateCase($testCase, $request->validated()),
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TestCase $testCase): JsonResponse
    {
        Gate::authorize('delete', $testCase);

        $testCase->delete();

        return response()->json([
            'message' => 'Test case deleted.',
        ]);
    }

    /**
     * @return array{search: string, module_id: int|null, priority: string, automation_status: string, test_behavior: string, test_type: string, per_page: int}
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{search?: string, module_id?: int, priority?: string, automation_status?: string, test_behavior?: string, test_type?: string, per_page?: int} $validated */
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'string'],
            'automation_status' => ['nullable', 'string'],
            'test_behavior' => ['nullable', 'string'],
            'test_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'module_id' => isset($validated['module_id']) ? (int) $validated['module_id'] : null,
            'priority' => (string) ($validated['priority'] ?? 'all'),
            'automation_status' => (string) ($validated['automation_status'] ?? 'all'),
            'test_behavior' => (string) ($validated['test_behavior'] ?? 'all'),
            'test_type' => (string) ($validated['test_type'] ?? 'all'),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }

    private function activeProject(Request $request): ?Project
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');

        if (! $request->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        return $request->user()
            ->projects()
            ->whereKey((int) $activeProjectId)
            ->first();
    }

    /**
     * @return array{
     *     hasActiveProject: bool,
     *     activeProject: array{id: int, name: string}|null,
     *     testPlans: array<int, array{id: int, name: string}>,
     *     modules: array<int, array<string, mixed>>
     * }
     */
    private function aiGenerationOptions(Request $request): array
    {
        $activeProject = $this->activeProject($request);

        return [
            'hasActiveProject' => $activeProject instanceof Project,
            'activeProject' => $activeProject instanceof Project
                ? [
                    'id' => $activeProject->project_id,
                    'name' => $activeProject->project_name,
                ]
                : null,
            'testPlans' => $activeProject instanceof Project
                ? $this->testRepositoryService->getTestPlanOptionsForProject($activeProject)
                : [],
            'modules' => $activeProject instanceof Project
                ? TestRepositoryModuleResource::collection(
                    $this->testRepositoryService->getModulesForProject($activeProject),
                )->resolve()
                : [],
        ];
    }
}
