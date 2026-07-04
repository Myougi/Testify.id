<?php

namespace App\Http\Controllers;

use App\Http\Resources\TestCaseCollection;
use App\Http\Resources\TestPlanCollection;
use App\Http\Resources\TestPlanResource;
use App\Http\Resources\TestRepositoryModuleResource;
use App\Http\Resources\TestRunCollection;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Services\TestPlanPdfService;
use App\Services\TestPlanService;
use App\Services\TestRepositoryService;
use App\Services\TestRunService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestPlanPageController extends Controller
{
    public function __construct(
        public TestPlanService $testPlanService,
        public TestPlanPdfService $testPlanPdfService,
        public TestRepositoryService $testRepositoryService,
        public TestRunService $testRunService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TestPlan::class);

        $projects = $this->testPlanService->getProjectOptions($request->user());
        $activeProjectId = $this->activeProjectId($request);
        $selectedProjectId = $activeProjectId ?? $request->integer('project_id');

        if (! in_array($selectedProjectId, array_column($projects, 'id'), true)) {
            $selectedProjectId = null;
        }

        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => $request->string('status')->toString() ?: 'all',
            'priority' => $request->string('priority')->toString() ?: 'all',
            'per_page' => max(5, min(50, $request->integer('per_page', 10))),
        ];

        $payload = (new TestPlanCollection(
            $this->testPlanService->paginateForUser($request->user(), $filters, $activeProjectId),
        ))->additional([
            'stats' => $this->testPlanService->getStats($request->user(), $activeProjectId),
            'filters' => [
                ...$filters,
                'page' => $request->integer('page', 1),
            ],
            'options' => [
                'projects' => $projects,
                'statuses' => TestPlan::statuses(),
                'priorities' => TestPlan::priorities(),
                'selectedProjectId' => $selectedProjectId,
            ],
        ])->response()->getData(true);

        return Inertia::render('test-plans/Index', [
            'initialPayload' => $payload,
        ]);
    }

    public function show(Request $request, TestPlan $testPlan): Response
    {
        Gate::authorize('view', $testPlan);
        $this->abortWhenOutsideActiveProject($request, $testPlan->project_id);

        $filters = $this->availableCaseFilters($request);
        $detail = (new TestPlanResource(
            $this->testPlanService->getDetail($testPlan),
        ))->resolve();

        $availableCases = (new TestCaseCollection(
            $this->testPlanService->paginateAvailableCases($testPlan, $filters),
        ))->additional([
            'filters' => [
                ...$filters,
                'page' => $request->integer('page', 1),
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
        ])->response()->getData(true);
        $testRuns = (new TestRunCollection(
            $this->testRunService->paginateForTestPlan($testPlan),
        ))->response()->getData(true);

        return Inertia::render('test-plans/Show', [
            'initialTestPlan' => $detail,
            'initialAvailableCases' => $availableCases,
            'initialTestRuns' => $testRuns,
        ]);
    }

    public function export(Request $request, TestPlan $testPlan): HttpResponse
    {
        Gate::authorize('view', $testPlan);
        $this->abortWhenOutsideActiveProject($request, $testPlan->project_id);

        return $this->testPlanPdfService->download($testPlan);
    }

    /**
     * @return array{search: string, module_id: int|null, priority: string, automation_status: string, test_behavior: string, test_type: string, per_page: int}
     */
    private function availableCaseFilters(Request $request): array
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
