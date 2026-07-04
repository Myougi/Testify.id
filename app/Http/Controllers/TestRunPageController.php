<?php

namespace App\Http\Controllers;

use App\Http\Resources\TestRunCollection;
use App\Http\Resources\TestRunResource;
use App\Models\TestRun;
use App\Services\TestRunService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestRunPageController extends Controller
{
    public function __construct(public TestRunService $testRunService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TestRun::class);

        $filters = $this->validatedFilters($request);
        $activeProjectId = $this->activeProjectId($request);

        $payload = (new TestRunCollection(
            $this->testRunService->paginateForUser($request->user(), $filters, $activeProjectId),
        ))->additional([
            'stats' => $this->testRunService->getStats($request->user(), $activeProjectId),
            'filters' => [
                ...$filters,
                'page' => $request->integer('page', 1),
            ],
            'options' => [
                'testPlans' => $this->testRunService->getTestPlanOptions($request->user(), $activeProjectId),
                'environments' => TestRun::environments(),
                'priorities' => TestRun::priorities(),
                'statuses' => TestRun::statuses(),
                'caseStatuses' => TestRun::caseStatuses(),
            ],
        ])->response()->getData(true);

        return Inertia::render('test-executions/Index', [
            'initialPayload' => $payload,
        ]);
    }

    public function show(Request $request, TestRun $testRun): Response
    {
        Gate::authorize('view', $testRun);
        $this->abortWhenOutsideActiveProject($request, $testRun->testPlan->project_id);

        $payload = (new TestRunResource(
            $this->testRunService->getDetail($testRun),
        ))->response()->getData(true);

        return Inertia::render('test-executions/Show', [
            'initialTestRun' => $payload['data'],
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
