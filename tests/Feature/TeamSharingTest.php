<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('owners can create multiple teams', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('teams.store'), [
        'name' => 'Core QA',
    ])->assertRedirect(route('teams.index'));

    $this->actingAs($owner)->post(route('teams.store'), [
        'name' => 'Release Review',
    ])->assertRedirect(route('teams.index'));

    $this->assertDatabaseCount('teams', 2);
    $this->assertDatabaseHas('teams', [
        'owner_user_id' => $owner->id,
        'name' => 'Core QA',
    ]);
    $this->assertDatabaseHas('teams', [
        'owner_user_id' => $owner->id,
        'name' => 'Release Review',
    ]);
});

test('adding an existing user creates membership immediately and grants shared project access', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->for($owner)->create([
        'project_name' => 'Shared Mobile App',
    ]);
    $team = Team::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)->post(route('teams.members.store', $team), [
        'email' => $member->email,
    ])->assertRedirect(route('teams.index'));

    $this->actingAs($owner)->post(route('teams.project-access.store', $team), [
        'project_id' => $project->project_id,
    ])->assertRedirect(route('teams.index'));

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $member->id,
    ]);
    $this->assertDatabaseHas('project_team_accesses', [
        'team_id' => $team->id,
        'project_id' => $project->project_id,
    ]);

    $this->actingAs($member)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->has('projects', 1)
            ->where('projects.0.project_id', $project->project_id)
            ->where('projects.0.access_type', 'shared')
            ->where('projects.0.is_owned', false));

    $this->actingAs($member)
        ->get(route('projects.workspace', $project))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('qa_workspace_project_id', $project->project_id);

    $this->actingAs($member)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($member)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->getJson(route('api.test-cases.index'))
        ->assertOk();
});

test('adding an unregistered email creates an invitation and sends mail', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)->post(route('teams.members.store', $team), [
        'email' => 'invitee@example.com',
    ])->assertRedirect(route('teams.index'));

    $invitation = TeamInvitation::query()->first();

    expect($invitation)->not->toBeNull();

    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'invitee@example.com',
        'accepted_at' => null,
    ]);

    Notification::assertSentOnDemand(
        TeamInvitationNotification::class,
        fn (TeamInvitationNotification $notification): bool => $notification->invitation->is($invitation),
    );
});

test('invited users can register from the invite link and join automatically', function (): void {
    $owner = User::factory()->create();
    $team = Team::factory()->for($owner, 'owner')->create([
        'name' => 'Web QA Guild',
    ]);

    $this->actingAs($owner)->post(route('teams.members.store', $team), [
        'email' => 'new.member@example.com',
    ])->assertRedirect(route('teams.index'));

    /** @var TeamInvitation $invitation */
    $invitation = TeamInvitation::query()->firstOrFail();

    auth()->logout();

    $this->post(route('team-invitations.register', $invitation), [
        'name' => 'New Member',
        'email' => 'new.member@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('projects.index'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'new.member@example.com',
        'name' => 'New Member',
    ]);

    $newUser = User::query()->where('email', 'new.member@example.com')->firstOrFail();

    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $newUser->id,
    ]);

    expect($invitation->fresh()?->accepted_at)->not->toBeNull();
});

test('shared members cannot manage teams or edit shared projects', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->for($owner, 'owner')->create();
    $project = Project::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('teams.members.store', $team), [
        'email' => $member->email,
    ]);
    $this->actingAs($owner)->post(route('teams.project-access.store', $team), [
        'project_id' => $project->project_id,
    ]);

    $this->actingAs($member)->post(route('teams.members.store', $team), [
        'email' => 'another@example.com',
    ])->assertForbidden();

    $this->actingAs($member)->delete(route('teams.destroy', $team))
        ->assertForbidden();

    $this->actingAs($member)->put(route('projects.update', $project), [
        'project_name' => 'Should Fail',
        'project_description' => null,
        'project_url' => null,
    ])->assertForbidden();
});

test('shared active project session is cleared after access is removed', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->for($owner, 'owner')->create();
    $project = Project::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('teams.members.store', $team), [
        'email' => $member->email,
    ]);
    $this->actingAs($owner)->post(route('teams.project-access.store', $team), [
        'project_id' => $project->project_id,
    ]);

    $this->actingAs($member)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSessionHas('qa_workspace_project_id', $project->project_id);

    $projectAccessId = $team->fresh()->projects()->firstOrFail()->pivot->id;

    $this->actingAs($owner)
        ->delete(route('teams.project-access.destroy', [
            'team' => $team,
            'projectTeamAccess' => $projectAccessId,
        ]))
        ->assertRedirect(route('teams.index'));

    $this->actingAs($member)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSessionMissing('qa_workspace_project_id');
});
