<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\AcceptTeamInvitationRequest;
use App\Http\Requests\RegisterFromTeamInvitationRequest;
use App\Models\TeamInvitation;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    public function __construct(public TeamService $teamService) {}

    public function show(Request $request, TeamInvitation $teamInvitation): Response
    {
        $teamInvitation->load('team.owner');

        $currentUser = $request->user();
        $existingAccount = Auth::getProvider()
            ->retrieveByCredentials(['email' => $teamInvitation->email]);

        return Inertia::render('team-invitations/Show', [
            'invitation' => [
                'token' => $teamInvitation->token,
                'email' => $teamInvitation->email,
                'teamName' => $teamInvitation->team?->name,
                'inviterName' => $teamInvitation->team?->owner?->name,
                'expiresAt' => $teamInvitation->expires_at?->toIso8601String(),
                'acceptedAt' => $teamInvitation->accepted_at?->toIso8601String(),
                'isPending' => $teamInvitation->isPending(),
                'hasExistingAccount' => $existingAccount !== null,
                'canAcceptAsCurrentUser' => $currentUser !== null
                    && strcasecmp($currentUser->email, $teamInvitation->email) === 0,
                'loginRedirect' => route('login', [
                    'email' => $teamInvitation->email,
                    'redirect' => route('team-invitations.show', $teamInvitation, false),
                ], false),
                'registerRedirect' => route('register', [
                    'team_invitation' => $teamInvitation->token,
                ], false),
            ],
        ]);
    }

    public function accept(AcceptTeamInvitationRequest $request, TeamInvitation $teamInvitation): RedirectResponse
    {
        $this->teamService->acceptInvitation($teamInvitation, $request->user());

        return to_route('projects.index')->with('toast', [
            'type' => 'success',
            'message' => 'Team invitation accepted.',
        ]);
    }

    public function register(
        RegisterFromTeamInvitationRequest $request,
        TeamInvitation $teamInvitation,
        CreateNewUser $createNewUser,
    ): RedirectResponse {
        $user = $createNewUser->create($request->only([
            'name',
            'email',
            'password',
            'password_confirmation',
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        $this->teamService->acceptInvitation($teamInvitation, $user);

        return to_route('projects.index')->with('toast', [
            'type' => 'success',
            'message' => 'Your account is ready and the team invitation was accepted.',
        ]);
    }
}
