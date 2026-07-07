<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Resources\IssueCollection;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\Project;
use App\Services\IssueService;
use App\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class IssueController extends Controller
{
    public function __construct(
        public IssueService $issueService,
        public ProjectAccessService $projectAccessService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse|IssueCollection
    {
        Gate::authorize('viewAny', Issue::class);

        $filters = $this->validatedFilters($request);
        $activeProject = $this->activeProject($request);

        if (! $activeProject) {
            return response()->json($this->emptyPayload($request, $filters));
        }

        return (new IssueCollection(
            $this->issueService->paginateForProject($activeProject, $filters),
        ))->additional([
            'stats' => $this->issueService->getStats($activeProject),
            'filters' => [
                ...$filters,
                'page' => $request->integer('page', 1),
            ],
            'options' => [
                'hasActiveProject' => true,
                'testPlans' => $this->issueService->getTestPlanOptions($activeProject),
                'testRuns' => $this->issueService->getTestRunOptions($activeProject),
                'priorities' => Issue::priorities(),
                'severities' => Issue::severities(),
                'statuses' => Issue::statuses(),
                'environments' => Issue::environments(),
                'defectTypes' => Issue::defectTypes(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueRequest $request): JsonResponse
    {
        $project = $request->activeProject();

        abort_unless($project instanceof Project, 422, 'An active project is required.');

        return (new IssueResource(
            $this->issueService->store($project, $request->validated()),
        ))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Issue $issue): IssueResource
    {
        Gate::authorize('view', $issue);

        return new IssueResource(
            $this->issueService->getDetail($issue),
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIssueRequest $request, Issue $issue): IssueResource
    {
        return new IssueResource(
            $this->issueService->update($issue, $request->validated()),
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Issue $issue): JsonResponse
    {
        Gate::authorize('delete', $issue);

        $this->issueService->delete($issue);

        return response()->json([
            'message' => 'Issue deleted.',
        ]);
    }

    /**
     * @return array{search: string, severity: string, status: string, per_page: int}
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{search?: string, severity?: string, status?: string, per_page?: int} $validated */
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'severity' => (string) ($validated['severity'] ?? 'all'),
            'status' => (string) ($validated['status'] ?? 'all'),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }

    private function activeProject(Request $request): ?Project
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');

        if (! $request->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        return $this->projectAccessService
            ->accessibleProjectsQuery($request->user())
            ->whereKey((int) $activeProjectId)
            ->first();
    }

    /**
     * @param  array{search: string, severity: string, status: string, per_page: int}  $filters
     * @return array<string, mixed>
     */
    private function emptyPayload(Request $request, array $filters): array
    {
        $paginator = new LengthAwarePaginator(
            [],
            0,
            $filters['per_page'],
            $request->integer('page', 1),
            ['path' => $request->url()],
        );

        return (new IssueCollection($paginator))->additional([
            'stats' => [
                'totalActiveIssues' => 0,
                'criticalIssues' => 0,
                'resolutionVelocityDays' => null,
            ],
            'filters' => [
                ...$filters,
                'page' => $request->integer('page', 1),
            ],
            'options' => [
                'hasActiveProject' => false,
                'testPlans' => [],
                'testRuns' => [],
                'priorities' => Issue::priorities(),
                'severities' => Issue::severities(),
                'statuses' => Issue::statuses(),
                'environments' => Issue::environments(),
                'defectTypes' => Issue::defectTypes(),
            ],
        ])->response()->getData(true);
    }
}
