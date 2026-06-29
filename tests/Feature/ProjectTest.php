<?php

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\User;

test('guests are redirected from projects to login', function () {
    $response = $this->get(route('projects.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the projects page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Core Banking API',
        'project_description' => 'Critical payment workflows.',
    ]);
    Issue::factory()->for($project, 'project')->create([
        'status' => Issue::STATUS_OPEN,
    ]);
    Issue::factory()->for($project, 'project')->create([
        'status' => Issue::STATUS_SOLVED,
        'closed_at' => now(),
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    TestCase::factory()->count(3)->for($project, 'project')->for($module, 'module')->create();
    TestPlan::factory()->for($project, 'project')->create([
        'status' => TestPlan::STATUS_DRAFT,
    ]);
    TestPlan::factory()->count(2)->for($project, 'project')->create([
        'status' => TestPlan::STATUS_IN_PROGRESS,
    ]);
    TestPlan::factory()->count(3)->for($project, 'project')->create([
        'status' => TestPlan::STATUS_COMPLETED,
    ]);
    TestPlan::factory()->for($project, 'project')->create([
        'status' => TestPlan::STATUS_ARCHIVED,
    ]);

    $response = $this->actingAs($user)->get(route('projects.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('projects/Index')
        ->has('projects', 1)
        ->where('projects.0.project_name', 'Core Banking API')
        ->where('projects.0.project_description', 'Critical payment workflows.')
        ->where('projects.0.test_cases_count', 3)
        ->where('projects.0.test_plans_count', 7)
        ->where('projects.0.active_test_plans_count', 2)
        ->where('projects.0.completed_test_plans_count', 3)
        ->where('projects.0.execution_progress', 43)
        ->where('projects.0.open_issues_count', 1)
        ->where('stats.totalProjects', 1)
        ->where('stats.activeProjects', 1)
        ->where('stats.inProgressProjects', 0)
        ->where('stats.archivedProjects', 0)
        ->where('status', 'all'));
});

test('users can filter active projects', function () {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    Project::query()->whereKey($activeProject->project_id)->update([
        'project_status' => 'Active',
    ]);

    $inProgressProject = Project::factory()->for($user)->create();
    Project::query()->whereKey($inProgressProject->project_id)->update([
        'project_status' => 'In Progress',
    ]);

    $archivedProject = Project::factory()->for($user)->create();
    Project::query()->whereKey($archivedProject->project_id)->update([
        'project_status' => 'Archived',
    ]);

    $response = $this->actingAs($user)->get(route('projects.index', [
        'status' => 'active',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('projects', 1)
        ->where('projects.0.project_id', $activeProject->project_id)
        ->where('status', 'active')
        ->where('stats.totalProjects', 3)
        ->where('stats.activeProjects', 1)
        ->where('stats.inProgressProjects', 1)
        ->where('stats.archivedProjects', 1));
});

test('users can filter in progress projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Project::query()->whereKey($project->project_id)->update([
        'project_status' => 'In Progress',
    ]);

    $response = $this->actingAs($user)->get(route('projects.index', [
        'status' => 'in-progress',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('projects', 1)
        ->where('projects.0.project_id', $project->project_id)
        ->where('status', 'in-progress')
        ->where('stats.totalProjects', 1)
        ->where('stats.activeProjects', 0)
        ->where('stats.inProgressProjects', 1)
        ->where('stats.archivedProjects', 0));
});

test('users can filter archived projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Project::query()->whereKey($project->project_id)->update([
        'project_status' => 'Archived',
    ]);

    $response = $this->actingAs($user)->get(route('projects.index', [
        'status' => 'archived',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('projects', 1)
        ->where('projects.0.project_id', $project->project_id)
        ->where('status', 'archived')
        ->where('stats.totalProjects', 1)
        ->where('stats.activeProjects', 0)
        ->where('stats.inProgressProjects', 0)
        ->where('stats.archivedProjects', 1));
});

test('users can create a project', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('projects.store'), [
        'project_name' => 'Mobile Banking App',
        'project_description' => 'Mobile regression suite.',
        'project_url' => 'https://example.com/mobile',
    ]);

    $response->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'project_name' => 'Mobile Banking App',
        'project_description' => 'Mobile regression suite.',
        'project_url' => 'https://example.com/mobile',
        'user_id' => $user->id,
    ]);
});

test('users can update their own projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this->actingAs($user)->put(route('projects.update', $project), [
        'project_name' => 'Cloud Infrastructure',
        'project_description' => null,
        'project_url' => null,
    ]);

    $response->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'project_id' => $project->project_id,
        'project_name' => 'Cloud Infrastructure',
        'project_description' => null,
        'project_url' => null,
    ]);
});

test('users can select a project for the qa workspace', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Xendit Payment Gateway',
    ]);

    $response = $this->actingAs($user)->get(route('projects.workspace', $project));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('qa_workspace_project_id', $project->project_id);
});

test('users cannot update projects owned by others', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)->put(route('projects.update', $project), [
        'project_name' => 'Renamed Project',
        'project_description' => null,
        'project_url' => null,
    ]);

    $response->assertForbidden();
});

test('users can delete their own projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->delete(route('projects.destroy', $project));

    $response->assertRedirect(route('projects.index'));
    $response->assertSessionMissing('qa_workspace_project_id');

    $this->assertSoftDeleted('projects', [
        'project_id' => $project->project_id,
    ]);
});

test('users cannot delete projects owned by others', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)->delete(route('projects.destroy', $project));

    $response->assertForbidden();
});
