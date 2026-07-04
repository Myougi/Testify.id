<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestRunRequest;
use App\Http\Requests\UpdateTestRunCaseStatusRequest;
use App\Http\Requests\UpdateTestRunRequest;
use App\Http\Resources\TestRunCollection;
use App\Http\Resources\TestRunResource;
use App\Models\TestCase;
use App\Models\TestRun;
use App\Services\TestRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestRunController extends Controller
{
    public function __construct(public TestRunService $testRunService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): TestRunCollection
    {
        Gate::authorize('viewAny', TestRun::class);

        $filters = $this->validatedFilters($request);
        $activeProjectId = $this->activeProjectId($request);

        return (new TestRunCollection(
            $this->testRunService->paginateForUser($request->user(), $filters, $activeProjectId),
        ))->additional([
            'stats' => $this->testRunService->getStats($request->user(), $activeProjectId),
            'filters' => [
                ...$filters,
                'page' => (int) $request->integer('page', 1),
            ],
            'options' => [
                'testPlans' => $this->testRunService->getTestPlanOptions($request->user(), $activeProjectId),
                'environments' => TestRun::environments(),
                'priorities' => TestRun::priorities(),
                'statuses' => TestRun::statuses(),
                'caseStatuses' => TestRun::caseStatuses(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTestRunRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $testCaseIds = array_map('intval', $validated['test_case_ids']);
        unset($validated['test_case_ids']);

        return (new TestRunResource(
            $this->testRunService->store($validated, $testCaseIds),
        ))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, TestRun $testRun): TestRunResource
    {
        Gate::authorize('view', $testRun);
        $this->abortWhenOutsideActiveProject($request, $testRun->testPlan->project_id);

        return new TestRunResource(
            $this->testRunService->getDetail($testRun),
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTestRunRequest $request, TestRun $testRun): TestRunResource
    {
        $this->abortWhenOutsideActiveProject($request, $testRun->testPlan->project_id);

        $validated = $request->validated();
        $testCaseIds = array_map('intval', $validated['test_case_ids']);
        unset($validated['test_case_ids']);

        return new TestRunResource(
            $this->testRunService->update($testRun, $validated, $testCaseIds),
        );
    }

    public function start(Request $request, TestRun $testRun): TestRunResource
    {
        Gate::authorize('update', $testRun);
        $this->abortWhenOutsideActiveProject($request, $testRun->testPlan->project_id);

        return new TestRunResource(
            $this->testRunService->start($testRun),
        );
    }

    public function updateCaseStatus(
        UpdateTestRunCaseStatusRequest $request,
        TestRun $testRun,
        TestCase $testCase,
    ): TestRunResource {
        $this->abortWhenOutsideActiveProject($request, $testRun->testPlan->project_id);

        return new TestRunResource(
            $this->testRunService->updateCaseStatus(
                $testRun,
                $testCase,
                (string) $request->validated('status'),
            ),
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TestRun $testRun): JsonResponse
    {
        Gate::authorize('delete', $testRun);
        $this->abortWhenOutsideActiveProject(request(), $testRun->testPlan->project_id);

        $testRun->delete();

        return response()->json([
            'message' => 'Test run deleted.',
        ]);
    }

    /**
     * @return array{search: string, status: string, environment: string, priority: string, test_plan_id: int|null, per_page: int}
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{search?: string, status?: string, environment?: string, priority?: string, test_plan_id?: int, per_page?: int} $validated */
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'environment' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'test_plan_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => (string) ($validated['status'] ?? 'all'),
            'environment' => (string) ($validated['environment'] ?? 'all'),
            'priority' => (string) ($validated['priority'] ?? 'all'),
            'test_plan_id' => isset($validated['test_plan_id']) ? (int) $validated['test_plan_id'] : null,
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
