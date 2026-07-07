<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTeamAccess;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function createTeam(User $owner, array $data): Team
    {
        return $owner->ownedTeams()->create($data);
    }

    public function updateTeam(Team $team, array $data): Team
    {
        $team->update($data);

        return $team->fresh();
    }

    public function deleteTeam(Team $team): void
    {
        $team->delete();
    }

    /**
     * @return array{type: 'member'|'invitation', teamMember?: TeamMember, invitation?: TeamInvitation}
     */
    public function addMemberByEmail(Team $team, User $actor, string $email): array
    {
        $normalizedEmail = Str::lower(trim($email));

        if ($normalizedEmail === Str::lower($actor->email)) {
            throw ValidationException::withMessages([
                'email' => 'You cannot invite yourself to your own team.',
            ]);
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if ($existingUser instanceof User && $team->members()->whereKey($existingUser->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the team.',
            ]);
        }

        if ($team->invitations()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email.',
            ]);
        }

        return DB::transaction(function () use ($team, $actor, $normalizedEmail, $existingUser): array {
            if ($existingUser instanceof User) {
                $teamMember = TeamMember::query()->create([
                    'team_id' => $team->id,
                    'user_id' => $existingUser->id,
                    'added_by_user_id' => $actor->id,
                ]);

                return [
                    'type' => 'member',
                    'teamMember' => $teamMember,
                ];
            }

            $invitation = TeamInvitation::query()->create([
                'team_id' => $team->id,
                'email' => $normalizedEmail,
                'token' => Str::random(40),
                'invited_by_user_id' => $actor->id,
                'expires_at' => now()->addDays(7),
            ]);

            Notification::route('mail', $normalizedEmail)
                ->notify(new TeamInvitationNotification($invitation));

            return [
                'type' => 'invitation',
                'invitation' => $invitation,
            ];
        });
    }

    public function removeMember(Team $team, TeamMember $teamMember): void
    {
        if ($teamMember->team_id !== $team->id) {
            abort(404);
        }

        $teamMember->delete();
    }

    public function acceptInvitation(TeamInvitation $invitation, User $user): TeamMember
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        if (Str::lower($invitation->email) !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'This invitation does not belong to the authenticated user.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $user): TeamMember {
            $teamMember = TeamMember::query()->firstOrCreate([
                'team_id' => $invitation->team_id,
                'user_id' => $user->id,
            ], [
                'added_by_user_id' => $invitation->invited_by_user_id,
            ]);

            $invitation->forceFill([
                'invited_user_id' => $user->id,
                'accepted_at' => now(),
            ])->save();

            return $teamMember;
        });
    }

    public function shareProject(Team $team, Project $project, User $actor): ProjectTeamAccess
    {
        if ($project->user_id !== $actor->id || $team->owner_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'project_id' => 'Only the project owner can share this project with the team.',
            ]);
        }

        return ProjectTeamAccess::query()->firstOrCreate([
            'project_id' => $project->project_id,
            'team_id' => $team->id,
        ], [
            'granted_by_user_id' => $actor->id,
        ]);
    }

    public function unshareProject(Team $team, ProjectTeamAccess $projectTeamAccess): void
    {
        if ($projectTeamAccess->team_id !== $team->id) {
            abort(404);
        }

        $projectTeamAccess->delete();
    }
}
