<?php

namespace App\Http\Controllers;

use App\Http\Resources\TestCaseCollection;
use App\Http\Resources\TestCaseResource;
use App\Http\Resources\TestRepositoryModuleResource;
use App\Models\Project;
use App\Models\TestCase;
use App\Services\ProjectAccessService;
use App\Services\TestCaseGenerationCountResolver;
use App\Services\TestRepositoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TestRepositoryPageController extends Controller
{
    public function __construct(
        public TestRepositoryService $testRepositoryService,
        public TestCaseGenerationCountResolver $testCaseGenerationCountResolver,
        public ProjectAccessService $projectAccessService,
    ) {}

    /**
     * Display the repository workspace.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', TestCase::class);

        $filters = $this->validatedFilters($request);
        $activeProjectId = $this->activeProjectId($request);
        $payload = (new TestCaseCollection(
            $this->testRepositoryService->paginateCasesForUser($request->user(), $filters, $activeProjectId),
        ))->additional([
            'stats' => $this->testRepositoryService->getStats($request->user(), $activeProjectId),
            'filters' => [
                ...$filters,
                'page' => $request->integer('page', 1),
            ],
            'options' => $this->optionsFor($request),
        ])->response()->getData(true);

        return Inertia::render('test-repositories/Index', [
            'initialPayload' => $payload,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', TestCase::class);

        return Inertia::render('test-repositories/Create', [
            'options' => $this->optionsFor($request),
            'selectedModuleId' => $request->integer('module_id') ?: null,
        ]);
    }

    public function edit(TestCase $testCase, Request $request): Response
    {
        Gate::authorize('update', $testCase);
        $this->abortWhenOutsideActiveProject($request, $testCase->project_id);

        return Inertia::render('test-repositories/Edit', [
            'testCase' => (new TestCaseResource($testCase->load(['project', 'module', 'steps'])))->resolve(),
            'options' => $this->optionsFor($request),
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

    /**
     * @return array<string, mixed>
     */
    private function optionsFor(Request $request): array
    {
        $activeProject = $this->activeProject($request);
        $activeProjectId = $activeProject?->project_id;

        return [
            'projects' => $this->testRepositoryService->getProjectOptions($request->user()),
            'modules' => TestRepositoryModuleResource::collection(
                $this->testRepositoryService->getModulesForUser($request->user(), $activeProjectId),
            )->resolve(),
            'priorities' => TestCase::priorities(),
            'automationStatuses' => TestCase::automationStatuses(),
            'behaviors' => TestCase::behaviors(),
            'types' => TestCase::types(),
            'aiGeneration' => [
                'generationLimit' => $this->testCaseGenerationCountResolver->maximum(),
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
            ],
        ];
    }

    private function activeProject(Request $request): ?Project
    {
        $activeProjectId = $this->activeProjectId($request);

        if (! $request->user() || $activeProjectId === null) {
            return null;
        }

        return $this->projectAccessService
            ->accessibleProjectsQuery($request->user())
            ->whereKey($activeProjectId)
            ->first();
    }

    private function activeProjectId(Request $request): ?int
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');

        if (! $request->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        $projectExists = $this->projectAccessService
            ->accessibleProjectsQuery($request->user())
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
