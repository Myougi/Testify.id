<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTestPlanCasesRequest;
use App\Http\Requests\StoreTestPlanRequest;
use App\Http\Requests\UpdateTestPlanRequest;
use App\Http\Resources\TestCaseCollection;
use App\Http\Resources\TestPlanCollection;
use App\Http\Resources\TestPlanResource;
use App\Http\Resources\TestRepositoryModuleResource;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Services\TestPlanService;
use App\Services\TestRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestPlanController extends Controller
{
    public function __construct(
        public TestPlanService $testPlanService,
        public TestRepositoryService $testRepositoryService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): TestPlanCollection
    {
        Gate::authorize('viewAny', TestPlan::class);

        $filters = $this->validatedFilters($request);
        $activeProjectId = $this->activeProjectId($request);

        return (new TestPlanCollection(
            $this->testPlanService->paginateForUser($request->user(), $filters, $activeProjectId),
        ))->additional([
            'stats' => $this->testPlanService->getStats($request->user(), $activeProjectId),
            'filters' => [
                ...$filters,
                'page' => (int) $request->integer('page', 1),
            ],
            'options' => [
                'projects' => $this->testPlanService->getProjectOptions($request->user()),
                'statuses' => TestPlan::statuses(),
                'priorities' => TestPlan::priorities(),
                'selectedProjectId' => null,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTestPlanRequest $request): JsonResponse
    {
        return (new TestPlanResource(
            $this->testPlanService->store($request->validated()),
        ))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, TestPlan $testPlan): TestPlanResource
    {
        Gate::authorize('view', $testPlan);
        $this->abortWhenOutsideActiveProject($request, $testPlan->project_id);

        return new TestPlanResource(
            $this->testPlanService->getDetail($testPlan),
        );
    }

    public function availableTestCases(Request $request, TestPlan $testPlan): TestCaseCollection
    {
        Gate::authorize('view', $testPlan);
        $this->abortWhenOutsideActiveProject($request, $testPlan->project_id);

        $filters = $this->validatedAvailableCaseFilters($request);

        return (new TestCaseCollection(
            $this->testPlanService->paginateAvailableCases($testPlan, $filters),
        ))->additional([
            'filters' => [
                ...$filters,
                'page' => (int) $request->integer('page', 1),
            ],
            'options' => [
                'modules' => TestRepositoryModuleResource::collection(
                    $this->testRepositoryService->getModulesForProject($testPlan->project),
                )->resolve(),
                'priorities' => TestCase::priorities(),
                'automationStatuses' => TestCase::automationStatuses(),
                'behaviors' => TestCase::behaviors(),
                'types' => TestCase::types(),
            ],
        ]);
    }

    public function attachTestCases(
        AssignTestPlanCasesRequest $request,
        TestPlan $testPlan,
    ): JsonResponse {
        $this->abortWhenOutsideActiveProject($request, $testPlan->project_id);

        $updatedPlan = $this->testPlanService->attachTestCases(
            $testPlan,
            array_map('intval', $request->validated('test_case_ids')),
        );

        return response()->json([
            'message' => 'Test cases added to the plan.',
            'data' => (new TestPlanResource($updatedPlan))->resolve(),
        ]);
    }

    public function detachTestCase(TestPlan $testPlan, TestCase $testCase): JsonResponse
    {
        Gate::authorize('update', $testPlan);
        $this->abortWhenOutsideActiveProject(request(), $testPlan->project_id);

        $updatedPlan = $this->testPlanService->detachTestCase($testPlan, $testCase);

        return response()->json([
            'message' => 'Test case removed from the plan.',
            'data' => (new TestPlanResource($updatedPlan))->resolve(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTestPlanRequest $request, TestPlan $testPlan): TestPlanResource
    {
        $this->abortWhenOutsideActiveProject($request, $testPlan->project_id);

        return new TestPlanResource(
            $this->testPlanService->update($testPlan, $request->validated()),
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TestPlan $testPlan): JsonResponse
    {
        Gate::authorize('delete', $testPlan);
        $this->abortWhenOutsideActiveProject(request(), $testPlan->project_id);

        $testPlan->delete();

        return response()->json([
            'message' => 'Test plan deleted.',
        ]);
    }

    /**
     * @return array{search: string, status: string, priority: string, per_page: int}
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{search?: string, status?: string, priority?: string, per_page?: int} $validated */
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => (string) ($validated['status'] ?? 'all'),
            'priority' => (string) ($validated['priority'] ?? 'all'),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }

    /**
     * @return array{search: string, module_id: int|null, priority: string, automation_status: string, test_behavior: string, test_type: string, per_page: int}
     */
    private function validatedAvailableCaseFilters(Request $request): array
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

    private function activeProjectId(Request $request): ?int
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');

        if (! $request->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        $projectExists = $request->user()
            ->projects()
            ->whereKey((int) $activeProjectId)
            ->exists();

        return $projectExists ? (int) $activeProjectId : null;
    }

    private function abortWhenOutsideActiveProject(Request $request, int $projectId): void
    {
        $activeProjectId = $this->activeProjectId($request);

        if ($activeProjectId !== null && $activeProjectId !== $projectId) {
            throw new NotFoundHttpException;
        }
    }
}
