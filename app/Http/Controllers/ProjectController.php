<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TestPlan;
use App\Services\ProjectAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(public ProjectAccessService $projectAccessService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projectsQuery = $this->projectAccessService
            ->accessibleProjectsQuery($request->user())
            ->latest();
        $ownerId = $request->user()->id;
        $status = $request->string('status')->lower()->toString();

        $counts = [
            'totalProjects' => (clone $projectsQuery)->count(),
            'activeProjects' => (clone $projectsQuery)
                ->where('project_status', 'Active')
                ->count(),
            'inProgressProjects' => (clone $projectsQuery)
                ->where('project_status', 'In Progress')
                ->count(),
            'archivedProjects' => (clone $projectsQuery)
                ->where('project_status', 'Archived')
                ->count(),
        ];

        $projects = (match ($status) {
            'active' => (clone $projectsQuery)->where('project_status', 'Active'),
            'in-progress' => (clone $projectsQuery)
                ->where('project_status', 'In Progress'),
            'archived' => (clone $projectsQuery)->where('project_status', 'Archived'),
            default => $projectsQuery,
        })
            ->withCount([
                'testCases',
                'testPlans',
                'testPlans as active_test_plans_count' => fn ($query) => $query
                    ->where('status', TestPlan::STATUS_IN_PROGRESS),
                'testPlans as completed_test_plans_count' => fn ($query) => $query
                    ->where('status', TestPlan::STATUS_COMPLETED),
                'issues as open_issues_count' => fn ($query) => $query
                    ->where('status', '!=', Issue::STATUS_SOLVED),
            ])
            ->get([
                'project_id',
                'project_name',
                'project_description',
                'project_url',
                'project_status',
                'user_id',
                'created_at',
            ])
            ->each(function (Project $project) use ($ownerId): void {
                $project->setAttribute(
                    'execution_progress',
                    $project->test_plans_count === 0
                        ? 0
                        : (int) round(
                            ($project->completed_test_plans_count / $project->test_plans_count) * 100
                        ),
                );
                $project->setAttribute('is_owned', $project->user_id === $ownerId);
                $project->setAttribute(
                    'access_type',
                    $project->user_id === $ownerId ? 'owned' : 'shared',
                );
            });

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'status' => $status !== '' ? $status : 'all',
            'stats' => $counts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $request->user()->projects()->create($request->validated());

        return to_route('projects.index')->with('toast', [
            'type' => 'success',
            'message' => 'Project created.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return to_route('projects.index')->with('toast', [
            'type' => 'success',
            'message' => 'Project updated.',
        ]);
    }

    /**
     * Set the selected project for the QA workspace.
     */
    public function workspace(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        $request->session()->put('qa_workspace_project_id', $project->project_id);

        return to_route('dashboard')->with('toast', [
            'type' => 'success',
            'message' => 'Project selected.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        if ($request->session()->get('qa_workspace_project_id') === $project->project_id) {
            $request->session()->forget('qa_workspace_project_id');
        }

        $project->delete();

        return to_route('projects.index')->with('toast', [
            'type' => 'success',
            'message' => 'Project deleted.',
        ]);
    }
}
