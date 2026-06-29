<?php

use App\Models\Project;
use App\Models\TestPlan;
use App\Models\User;

test('qa workspace pages render with the new layout', function (string $routeName, string $component): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'QA Suite Project',
    ]);

    if ($routeName === 'test-plans.index') {
        TestPlan::factory()->create([
            'project_id' => $project->project_id,
            'tester' => $user->email,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route($routeName));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component($component)
        ->where('activeProject.name', 'QA Suite Project'));
})->with([
    ['dashboard', 'Dashboard'],
    ['test-plans.index', 'test-plans/Index'],
    ['test-repositories.index', 'test-repositories/Index'],
    ['test-executions.index', 'test-executions/Index'],
    ['issues.index', 'issues/Index'],
    ['reports.index', 'reports/Index'],
]);
