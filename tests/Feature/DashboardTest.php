<?php

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected to the login page', function (): void {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard with an empty payload when no active project is selected', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('initialPayload.options.hasActiveProject', false)
        ->where('initialPayload.options.project', null)
        ->where('initialPayload.metrics.totalTestCases', 0)
        ->where('initialPayload.workload', [])
        ->where('initialPayload.recentIssues', [])
        ->where('initialPayload.overduePlans', []));
});

test('dashboard metrics are scoped to the active project and use latest execution status', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Checkout Platform',
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $cases = TestCase::factory()
        ->count(5)
        ->for($project, 'project')
        ->for($module, 'module')
        ->create();
    $testPlan->testCases()->attach($cases->pluck('id')->all());

    $completedRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_COMPLETED,
    ]);
    $completedRun->testCases()->attach([
        $cases[0]->id => ['execution_status' => TestRun::CASE_STATUS_PASSED],
        $cases[1]->id => ['execution_status' => TestRun::CASE_STATUS_FAILED],
    ]);

    $activeRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $activeRun->testCases()->attach([
        $cases[2]->id => ['execution_status' => TestRun::CASE_STATUS_IN_TESTING],
        $cases[3]->id => ['execution_status' => TestRun::CASE_STATUS_SKIPPED],
    ]);

    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $otherModule = TestRepositoryModule::factory()->for($otherProject, 'project')->create();
    TestCase::factory()->count(3)->for($otherProject, 'project')->for($otherModule, 'module')->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('initialPayload.options.hasActiveProject', true)
        ->where('initialPayload.options.project.name', 'Checkout Platform')
        ->where('initialPayload.metrics.totalTestCases', 5)
        ->where('initialPayload.metrics.passed', 1)
        ->where('initialPayload.metrics.failed', 1)
        ->where('initialPayload.metrics.inTesting', 1)
        ->where('initialPayload.metrics.notRun', 2));
});

test('dashboard workload aggregates active runs by tester', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $cases = TestCase::factory()
        ->count(3)
        ->for($project, 'project')
        ->for($module, 'module')
        ->create();
    $testPlan->testCases()->attach($cases->pluck('id')->all());

    $firstRun = TestRun::factory()->for($testPlan)->create([
        'tester_name' => 'Sarah Smith',
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $firstRun->testCases()->attach([$cases[0]->id, $cases[1]->id]);

    $secondRun = TestRun::factory()->for($testPlan)->create([
        'tester_name' => 'Sarah Smith',
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $secondRun->testCases()->attach([$cases[2]->id]);

    TestRun::factory()->for($testPlan)->create([
        'tester_name' => 'Completed Tester',
        'status' => TestRun::STATUS_COMPLETED,
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('initialPayload.workload', 1)
        ->where('initialPayload.workload.0.name', 'Sarah Smith')
        ->where('initialPayload.workload.0.initials', 'SS')
        ->where('initialPayload.workload.0.activeRuns', 2)
        ->where('initialPayload.workload.0.assignedCases', 3));
});

test('dashboard recent issues are limited ordered and scoped to the active project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for(User::factory())->create();

    foreach (range(1, 6) as $index) {
        Issue::factory()->for($project, 'project')->create([
            'title' => "Visible Issue {$index}",
            'reporter' => "Reporter {$index}",
            'created_at' => Carbon::parse("2026-06-2{$index} 09:00:00"),
        ]);
    }

    Issue::factory()->for($otherProject, 'project')->create([
        'title' => 'Hidden Issue',
        'created_at' => Carbon::parse('2026-06-30 09:00:00'),
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('initialPayload.recentIssues', 5)
        ->where('initialPayload.recentIssues.0.title', 'Visible Issue 6')
        ->where('initialPayload.recentIssues.4.title', 'Visible Issue 2'));
});

test('dashboard overdue plans exclude completed and archived plans and calculate progress', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-23'));

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $overduePlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Mobile App Checkout Suite',
        'status' => TestPlan::STATUS_IN_PROGRESS,
        'tester' => 'Sarah Smith',
        'due_date' => '2026-06-20',
    ]);
    $completedPlan = TestPlan::factory()->for($project, 'project')->create([
        'status' => TestPlan::STATUS_COMPLETED,
        'due_date' => '2026-06-19',
    ]);
    $archivedPlan = TestPlan::factory()->for($project, 'project')->create([
        'status' => TestPlan::STATUS_ARCHIVED,
        'due_date' => '2026-06-18',
    ]);
    $futurePlan = TestPlan::factory()->for($project, 'project')->create([
        'status' => TestPlan::STATUS_IN_PROGRESS,
        'due_date' => '2026-06-24',
    ]);
    $cases = TestCase::factory()
        ->count(2)
        ->for($project, 'project')
        ->for($module, 'module')
        ->create();
    $overduePlan->testCases()->attach($cases->pluck('id')->all());
    $testRun = TestRun::factory()->for($overduePlan)->create([
        'status' => TestRun::STATUS_COMPLETED,
    ]);
    $testRun->testCases()->attach([
        $cases[0]->id => ['execution_status' => TestRun::CASE_STATUS_PASSED],
        $cases[1]->id => ['execution_status' => TestRun::CASE_STATUS_FAILED],
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('dashboard'));

    Carbon::setTestNow();

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('initialPayload.overduePlans', 1)
        ->where('initialPayload.overduePlans.0.id', $overduePlan->id)
        ->where('initialPayload.overduePlans.0.name', 'Mobile App Checkout Suite')
        ->where('initialPayload.overduePlans.0.assignedTo', 'Sarah Smith')
        ->where('initialPayload.overduePlans.0.dueDate', '2026-06-20')
        ->where('initialPayload.overduePlans.0.progress', 50));

    expect($completedPlan->exists)->toBeTrue();
    expect($archivedPlan->exists)->toBeTrue();
    expect($futurePlan->exists)->toBeTrue();
});
