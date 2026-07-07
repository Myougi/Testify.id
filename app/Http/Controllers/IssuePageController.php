<?php

namespace App\Http\Controllers;

use App\Http\Resources\IssueCollection;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\Project;
use App\Services\IssueService;
use App\Services\ProjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IssuePageController extends Controller
{
    public function __construct(
        public IssueService $issueService,
        public ProjectAccessService $projectAccessService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Issue::class);

        $filters = $this->validatedFilters($request);
        $activeProject = $this->activeProject($request);
        $payload = $activeProject
            ? (new IssueCollection(
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
            ])->response()->getData(true)
            : $this->emptyPayload($request, $filters);

        return Inertia::render('issues/Index', [
            'initialPayload' => $payload,
        ]);
    }

    public function show(Issue $issue): Response
    {
        Gate::authorize('view', $issue);

        $payload = (new IssueResource(
            $this->issueService->getDetail($issue),
        ))->response()->getData(true);

        return Inertia::render('issues/Show', [
            'initialIssue' => $payload['data'],
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
