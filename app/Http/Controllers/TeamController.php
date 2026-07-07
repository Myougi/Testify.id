<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectTeamAccessRequest;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Project;
use App\Models\ProjectTeamAccess;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function __construct(public TeamService $teamService) {}

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $this->teamService->createTeam($request->user(), $request->validated());

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => 'Team created.',
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->teamService->updateTeam($team, $request->validated());

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => 'Team updated.',
        ]);
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $this->teamService->deleteTeam($team);

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => 'Team deleted.',
        ]);
    }

    public function storeMember(StoreTeamMemberRequest $request, Team $team): RedirectResponse
    {
        $result = $this->teamService->addMemberByEmail(
            $team,
            $request->user(),
            (string) $request->validated('email'),
        );

        $message = $result['type'] === 'member'
            ? 'Registered user added to the team.'
            : 'Invitation sent to the team member.';

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function destroyMember(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        Gate::authorize('update', $team);

        $this->teamService->removeMember($team, $teamMember);

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => 'Team member removed.',
        ]);
    }

    public function storeProjectAccess(StoreProjectTeamAccessRequest $request, Team $team): RedirectResponse
    {
        /** @var Project $project */
        $project = Project::query()
            ->whereKey($request->integer('project_id'))
            ->firstOrFail();

        $this->teamService->shareProject($team, $project, $request->user());

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => 'Project shared with the team.',
        ]);
    }

    public function destroyProjectAccess(Request $request, Team $team, ProjectTeamAccess $projectTeamAccess): RedirectResponse
    {
        Gate::authorize('update', $team);

        $this->teamService->unshareProject($team, $projectTeamAccess);

        return to_route('teams.index')->with('toast', [
            'type' => 'success',
            'message' => 'Project access removed from the team.',
        ]);
    }
}
