<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\ProjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamPageController extends Controller
{
    public function __construct(public ProjectAccessService $projectAccessService) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Team::class);

        $teams = $request->user()
            ->ownedTeams()
            ->with([
                'members:id,name,email',
                'invitations' => fn ($query) => $query
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->orderBy('created_at'),
                'projects:project_id,project_name,user_id',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members
                    ->map(fn ($member): array => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                    ])
                    ->values()
                    ->all(),
                'pendingInvitations' => $team->invitations
                    ->map(fn ($invitation): array => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'expires_at' => $invitation->expires_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'projects' => $team->projects
                    ->map(fn ($project): array => [
                        'projectTeamAccessId' => $project->pivot?->id,
                        'project_id' => $project->project_id,
                        'project_name' => $project->project_name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $shareableProjects = $this->projectAccessService
            ->ownedProjectsQuery($request->user())
            ->orderBy('project_name')
            ->get(['project_id', 'project_name'])
            ->map(fn ($project): array => [
                'project_id' => $project->project_id,
                'project_name' => $project->project_name,
            ])
            ->all();

        return Inertia::render('teams/Index', [
            'teams' => $teams,
            'shareableProjects' => $shareableProjects,
        ]);
    }
}
