<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected from the test executions page', function (): void {
    $response = $this->get(route('test-executions.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the test executions page', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Core Banking',
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Core Regression',
    ]);
    $testCase = TestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'case_key' => 'CORE-001',
            'title' => 'User can approve transfer',
        ]);
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'name' => 'Nightly Core Regression',
        'environment' => TestRun::ENVIRONMENT_STAGING,
        'priority' => TestRun::PRIORITY_URGENT,
        'tester_name' => 'Ari QA',
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->get(route('test-executions.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-executions/Index')
        ->has('initialPayload.data', 1)
        ->where('initialPayload.data.0.name', 'Nightly Core Regression')
        ->where('initialPayload.data.0.test_plan.name', 'Core Regression')
        ->where('initialPayload.data.0.priority', TestRun::PRIORITY_URGENT)
        ->where('initialPayload.data.0.test_cases.0.case_key', 'CORE-001')
        ->where('initialPayload.data.0.test_cases.0.execution_status', TestRun::CASE_STATUS_NOT_RUN)
        ->where('initialPayload.data.0.execution_summary.not_run', 1)
        ->where('initialPayload.data.0.execution_summary.progress', 0)
        ->where('initialPayload.stats.totalRuns', 1)
        ->where('initialPayload.stats.activeRuns', 1)
        ->where('initialPayload.options.testPlans.0.test_cases.0.case_key', 'CORE-001'));
});

test('authenticated users can visit a read only test run detail page', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Core Banking',
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Transfers',
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Core Regression',
    ]);
    $testCase = TestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'case_key' => 'CORE-010',
            'title' => 'User can review transfer status',
        ]);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'name' => 'Focused transfer run',
        'status' => TestRun::STATUS_COMPLETED,
    ]);
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->get(route('test-executions.show', $testRun));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-executions/Show')
        ->where('initialTestRun.id', $testRun->id)
        ->where('initialTestRun.name', 'Focused transfer run')
        ->where('initialTestRun.status', TestRun::STATUS_COMPLETED)
        ->where('initialTestRun.test_plan.name', 'Core Regression')
        ->where('initialTestRun.test_cases.0.case_key', 'CORE-010')
        ->where('initialTestRun.test_cases.0.execution_status', TestRun::CASE_STATUS_NOT_RUN)
        ->where('initialTestRun.execution_summary.not_run', 1)
        ->where('initialTestRun.execution_summary.progress', 0)
        ->where('initialTestRun.test_cases.0.module.name', 'Transfers'));
});

test('users cannot visit another users test run detail page', function (): void {
    $user = User::factory()->create();
    $otherProject = Project::factory()->for(User::factory())->create();
    $testPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);

    $response = $this->actingAs($user)->get(route('test-executions.show', $testRun));

    $response->assertForbidden();
});

test('users can list only test runs from their own plans', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $visibleRun = TestRun::factory()->for($testPlan)->create([
        'name' => 'Visible run',
    ]);

    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $otherPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    TestRun::factory()->for($otherPlan)->create([
        'name' => 'Hidden run',
    ]);

    $response = $this->actingAs($user)->getJson(route('api.test-runs.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $visibleRun->id);
    $response->assertJsonPath('data.0.name', 'Visible run');
});

test('users can create a test run with priority multiple selected plan cases and new status', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $firstCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $secondCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach([$firstCase->id, $secondCase->id]);

    $response = $this->actingAs($user)->postJson(route('api.test-runs.store'), [
        'test_plan_id' => $testPlan->id,
        'test_case_ids' => [$firstCase->id, $secondCase->id],
        'name' => 'Release Candidate Execution',
        'environment' => TestRun::ENVIRONMENT_UAT,
        'priority' => TestRun::PRIORITY_URGENT,
        'description' => 'Run candidate verification before release.',
        'tester_name' => 'Taylor Park',
        'status' => TestRun::STATUS_COMPLETED,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Release Candidate Execution');
    $response->assertJsonPath('data.priority', TestRun::PRIORITY_URGENT);
    $response->assertJsonPath('data.status', TestRun::STATUS_NEW);
    $response->assertJsonPath('data.assigned_test_cases_count', 2);
    $response->assertJsonPath('data.execution_summary.not_run', 2);
    $response->assertJsonPath('data.execution_summary.progress', 0);

    $this->assertDatabaseHas('test_runs', [
        'test_plan_id' => $testPlan->id,
        'name' => 'Release Candidate Execution',
        'environment' => TestRun::ENVIRONMENT_UAT,
        'priority' => TestRun::PRIORITY_URGENT,
        'tester_name' => 'Taylor Park',
        'status' => TestRun::STATUS_NEW,
    ]);
    $this->assertDatabaseHas('test_case_test_run', [
        'test_case_id' => $firstCase->id,
        'execution_status' => TestRun::CASE_STATUS_NOT_RUN,
    ]);
    $this->assertDatabaseHas('test_case_test_run', [
        'test_case_id' => $secondCase->id,
        'execution_status' => TestRun::CASE_STATUS_NOT_RUN,
    ]);
});

test('users cannot create a test run for another users plan', function (): void {
    $user = User::factory()->create();
    $otherProject = Project::factory()->for(User::factory())->create();
    $module = TestRepositoryModule::factory()->for($otherProject, 'project')->create();
    $testPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $testCase = TestCase::factory()->for($otherProject, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);

    $response = $this->actingAs($user)->postJson(route('api.test-runs.store'), [
        'test_plan_id' => $testPlan->id,
        'test_case_ids' => [$testCase->id],
        'name' => 'Unauthorized run',
        'environment' => TestRun::ENVIRONMENT_STAGING,
        'priority' => TestRun::PRIORITY_MEDIUM,
        'tester_name' => 'Taylor Park',
        'status' => TestRun::STATUS_NEW,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['test_plan_id']);
});

test('users cannot attach cases that are not assigned to the selected plan', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $assignedCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $unassignedCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($assignedCase);

    $response = $this->actingAs($user)->postJson(route('api.test-runs.store'), [
        'test_plan_id' => $testPlan->id,
        'test_case_ids' => [$assignedCase->id, $unassignedCase->id],
        'name' => 'Invalid scope run',
        'environment' => TestRun::ENVIRONMENT_STAGING,
        'priority' => TestRun::PRIORITY_MEDIUM,
        'tester_name' => 'Taylor Park',
        'status' => TestRun::STATUS_NEW,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['test_case_ids']);
});

test('users can update test run metadata priority and selected cases', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $firstCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $secondCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach([$firstCase->id, $secondCase->id]);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'priority' => TestRun::PRIORITY_LOW,
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $testRun->testCases()->attach($firstCase);

    $response = $this->actingAs($user)->putJson(route('api.test-runs.update', $testRun), [
        'test_plan_id' => $testPlan->id,
        'test_case_ids' => [$secondCase->id],
        'name' => 'Updated execution run',
        'environment' => TestRun::ENVIRONMENT_PRODUCTION,
        'priority' => TestRun::PRIORITY_HIGH,
        'description' => null,
        'tester_name' => 'Morgan Lane',
        'status' => TestRun::STATUS_COMPLETED,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated execution run');
    $response->assertJsonPath('data.priority', TestRun::PRIORITY_HIGH);
    $response->assertJsonPath('data.status', TestRun::STATUS_IN_PROGRESS);
    $response->assertJsonPath('data.assigned_test_cases_count', 1);

    $this->assertDatabaseHas('test_runs', [
        'id' => $testRun->id,
        'name' => 'Updated execution run',
        'priority' => TestRun::PRIORITY_HIGH,
        'tester_name' => 'Morgan Lane',
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $this->assertDatabaseMissing('test_case_test_run', [
        'test_run_id' => $testRun->id,
        'test_case_id' => $firstCase->id,
    ]);
    $this->assertDatabaseHas('test_case_test_run', [
        'test_run_id' => $testRun->id,
        'test_case_id' => $secondCase->id,
        'execution_status' => TestRun::CASE_STATUS_NOT_RUN,
    ]);
});

test('users can start a new test run and active stats increase', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_NEW,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.test-runs.index'))
        ->assertOk()
        ->assertJsonPath('stats.newRuns', 1)
        ->assertJsonPath('stats.activeRuns', 0);

    $startedAt = Carbon::parse('2026-06-20 09:30:00');
    Carbon::setTestNow($startedAt);

    $response = $this->actingAs($user)->patchJson(route('api.test-runs.start', $testRun));

    Carbon::setTestNow();

    $response->assertOk();
    $response->assertJsonPath('data.status', TestRun::STATUS_IN_PROGRESS);
    $response->assertJsonPath('data.execution_started_at', $startedAt->toIso8601String());
    $response->assertJsonPath('data.execution_completed_at', null);

    $this->assertDatabaseHas('test_runs', [
        'id' => $testRun->id,
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    expect($testRun->fresh()->execution_started_at?->equalTo($startedAt))->toBeTrue();

    $this->actingAs($user)
        ->getJson(route('api.test-runs.index'))
        ->assertOk()
        ->assertJsonPath('stats.newRuns', 0)
        ->assertJsonPath('stats.activeRuns', 1);
});

test('users cannot start another users test run', function (): void {
    $user = User::factory()->create();
    $otherProject = Project::factory()->for(User::factory())->create();
    $testPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_NEW,
    ]);

    $response = $this->actingAs($user)->patchJson(route('api.test-runs.start', $testRun));

    $response->assertForbidden();
});

test('completed test runs cannot be started', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_COMPLETED,
    ]);

    $response = $this->actingAs($user)->patchJson(route('api.test-runs.start', $testRun));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

test('users cannot update execution status before starting a test run', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_NEW,
    ]);
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->patchJson(
        route('api.test-runs.test-cases.update-status', [$testRun, $testCase]),
        [
            'status' => TestRun::CASE_STATUS_PASSED,
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
    $this->assertDatabaseHas('test_case_test_run', [
        'test_run_id' => $testRun->id,
        'test_case_id' => $testCase->id,
        'execution_status' => TestRun::CASE_STATUS_NOT_RUN,
    ]);
});

test('users can update an attached test run case execution status', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
        'case_key' => 'PAY-001',
    ]);
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->patchJson(
        route('api.test-runs.test-cases.update-status', [$testRun, $testCase]),
        [
            'status' => TestRun::CASE_STATUS_PASSED,
        ],
    );

    $response->assertOk();
    $response->assertJsonPath('data.test_cases.0.case_key', 'PAY-001');
    $response->assertJsonPath('data.test_cases.0.execution_status', TestRun::CASE_STATUS_PASSED);
    $response->assertJsonPath('data.status', TestRun::STATUS_COMPLETED);
    $response->assertJsonPath('data.execution_summary.passed', 1);
    $response->assertJsonPath('data.execution_summary.in_testing', 0);
    $response->assertJsonPath('data.execution_summary.progress', 100);

    $this->assertDatabaseHas('test_case_test_run', [
        'test_run_id' => $testRun->id,
        'test_case_id' => $testCase->id,
        'execution_status' => TestRun::CASE_STATUS_PASSED,
    ]);
    $this->assertDatabaseHas('test_runs', [
        'id' => $testRun->id,
        'status' => TestRun::STATUS_COMPLETED,
    ]);
});

test('users can mark an attached test run case as in testing', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->patchJson(
        route('api.test-runs.test-cases.update-status', [$testRun, $testCase]),
        [
            'status' => TestRun::CASE_STATUS_IN_TESTING,
        ],
    );

    $response->assertOk();
    $response->assertJsonPath('data.test_cases.0.execution_status', TestRun::CASE_STATUS_IN_TESTING);
    $response->assertJsonPath('data.execution_summary.in_testing', 1);
    $response->assertJsonPath('data.execution_summary.passed', 0);
    $response->assertJsonPath('data.execution_summary.progress', 0);
    $response->assertJsonPath('data.status', TestRun::STATUS_IN_PROGRESS);

    $this->assertDatabaseHas('test_case_test_run', [
        'test_run_id' => $testRun->id,
        'test_case_id' => $testCase->id,
        'execution_status' => TestRun::CASE_STATUS_IN_TESTING,
    ]);
});

test('test run status completes only when every selected case is passed and reopens on correction', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $firstCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $secondCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach([$firstCase->id, $secondCase->id]);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $testRun->testCases()->attach([$firstCase->id, $secondCase->id]);

    $this->actingAs($user)
        ->patchJson(route('api.test-runs.test-cases.update-status', [$testRun, $firstCase]), [
            'status' => TestRun::CASE_STATUS_PASSED,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', TestRun::STATUS_IN_PROGRESS)
        ->assertJsonPath('data.execution_summary.progress', 50)
        ->assertJsonPath('data.execution_completed_at', null);

    $completedAt = Carbon::parse('2026-06-20 10:15:00');
    Carbon::setTestNow($completedAt);

    $this->actingAs($user)
        ->patchJson(route('api.test-runs.test-cases.update-status', [$testRun, $secondCase]), [
            'status' => TestRun::CASE_STATUS_PASSED,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', TestRun::STATUS_COMPLETED)
        ->assertJsonPath('data.execution_summary.progress', 100)
        ->assertJsonPath('data.execution_completed_at', $completedAt->toIso8601String());

    Carbon::setTestNow();

    $this->actingAs($user)
        ->patchJson(route('api.test-runs.test-cases.update-status', [$testRun, $firstCase]), [
            'status' => TestRun::CASE_STATUS_FAILED,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', TestRun::STATUS_IN_PROGRESS)
        ->assertJsonPath('data.execution_summary.progress', 50)
        ->assertJsonPath('data.execution_completed_at', null);
});

test('invalid test run case execution status values are rejected', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create();
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->patchJson(
        route('api.test-runs.test-cases.update-status', [$testRun, $testCase]),
        [
            'status' => 'Retested',
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});

test('users cannot update another users test run case execution status', function (): void {
    $user = User::factory()->create();
    $otherProject = Project::factory()->for(User::factory())->create();
    $module = TestRepositoryModule::factory()->for($otherProject, 'project')->create();
    $testPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $testCase = TestCase::factory()->for($otherProject, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create();
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->patchJson(
        route('api.test-runs.test-cases.update-status', [$testRun, $testCase]),
        [
            'status' => TestRun::CASE_STATUS_FAILED,
        ],
    );

    $response->assertForbidden();
});

test('users cannot update a test case that is not attached to the test run', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $attachedCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $unattachedCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach([$attachedCase->id, $unattachedCase->id]);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $testRun->testCases()->attach($attachedCase);

    $response = $this->actingAs($user)->patchJson(
        route('api.test-runs.test-cases.update-status', [$testRun, $unattachedCase]),
        [
            'status' => TestRun::CASE_STATUS_BLOCKED,
        ],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['test_case_id']);
});

test('test run progress counts only passed cases as completed', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCases = TestCase::factory()
        ->count(5)
        ->for($project, 'project')
        ->for($module, 'module')
        ->sequence(
            ['case_key' => 'SUM-001'],
            ['case_key' => 'SUM-002'],
            ['case_key' => 'SUM-003'],
            ['case_key' => 'SUM-004'],
            ['case_key' => 'SUM-005'],
        )
        ->create();
    $testPlan->testCases()->attach($testCases->pluck('id')->all());
    $testRun = TestRun::factory()->for($testPlan)->create();
    $testRun->testCases()->attach([
        $testCases[0]->id => ['execution_status' => TestRun::CASE_STATUS_PASSED],
        $testCases[1]->id => ['execution_status' => TestRun::CASE_STATUS_FAILED],
        $testCases[2]->id => ['execution_status' => TestRun::CASE_STATUS_SKIPPED],
        $testCases[3]->id => ['execution_status' => TestRun::CASE_STATUS_BLOCKED],
        $testCases[4]->id => ['execution_status' => TestRun::CASE_STATUS_NOT_RUN],
    ]);

    $response = $this->actingAs($user)->getJson(route('api.test-runs.show', $testRun));

    $response->assertOk();
    $response->assertJsonPath('data.execution_summary.passed', 1);
    $response->assertJsonPath('data.execution_summary.failed', 1);
    $response->assertJsonPath('data.execution_summary.skipped', 1);
    $response->assertJsonPath('data.execution_summary.blocked', 1);
    $response->assertJsonPath('data.execution_summary.not_run', 1);
    $response->assertJsonPath('data.execution_summary.completed', 1);
    $response->assertJsonPath('data.execution_summary.total', 5);
    $response->assertJsonPath('data.execution_summary.progress', 20);
});

test('users can soft delete a test run while preserving pivot rows for restoration', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);
    $testRun = TestRun::factory()->for($testPlan)->create();
    $testRun->testCases()->attach($testCase);

    $response = $this->actingAs($user)->deleteJson(route('api.test-runs.destroy', $testRun));

    $response->assertOk();
    $response->assertJsonPath('message', 'Test run deleted.');

    $this->assertSoftDeleted('test_runs', [
        'id' => $testRun->id,
    ]);
    $this->assertDatabaseHas('test_case_test_run', [
        'test_run_id' => $testRun->id,
        'test_case_id' => $testCase->id,
    ]);
});

test('environment and priority validation reject invalid enum values', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testPlan->testCases()->attach($testCase);

    $response = $this->actingAs($user)->postJson(route('api.test-runs.store'), [
        'test_plan_id' => $testPlan->id,
        'test_case_ids' => [$testCase->id],
        'name' => 'Invalid enum run',
        'environment' => 'Sandbox',
        'priority' => 'Critical',
        'tester_name' => 'Taylor Park',
        'status' => 'Blocked',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'environment',
        'priority',
    ]);
});
