<?php

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected from the issues page', function (): void {
    $response = $this->get(route('issues.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the issues page for the active project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Checkout Platform',
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Checkout Regression',
    ]);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'name' => 'Nightly Checkout Run',
    ]);

    $activeIssue = Issue::factory()->for($project, 'project')->create([
        'title' => 'Payment gateway timeout',
        'description' => 'Submit checkout with concurrent payment requests.',
        'priority' => Issue::PRIORITY_HIGH,
        'severity' => Issue::SEVERITY_CRITICAL,
        'status' => Issue::STATUS_OPEN,
        'reporter' => 'Ari QA',
        'test_plan_id' => $testPlan->id,
        'test_run_id' => $testRun->id,
    ]);

    $resolvedIssue = Issue::factory()->for($project, 'project')->create([
        'title' => 'Guest checkout field alignment issue',
        'description' => 'Open the guest checkout form on mobile viewport.',
        'severity' => Issue::SEVERITY_HIGH,
        'status' => Issue::STATUS_SOLVED,
        'reporter' => 'Morgan Lane',
    ]);

    $resolvedIssue->forceFill([
        'created_at' => Carbon::parse('2026-06-10 08:00:00'),
        'closed_at' => Carbon::parse('2026-06-14 08:00:00'),
    ])->save();

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->get(route('issues.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('issues/Index')
        ->has('initialPayload.data', 2)
        ->where('initialPayload.data.0.id', $activeIssue->id)
        ->where('initialPayload.data.0.title', 'Payment gateway timeout')
        ->where('initialPayload.data.0.description', 'Submit checkout with concurrent payment requests.')
        ->where('initialPayload.data.1.id', $resolvedIssue->id)
        ->where('initialPayload.stats.totalActiveIssues', 1)
        ->where('initialPayload.stats.criticalIssues', 1)
        ->where('initialPayload.stats.resolutionVelocityDays', 4)
        ->where('initialPayload.options.hasActiveProject', true)
        ->where('initialPayload.options.testPlans.0.id', $testPlan->id)
        ->where('initialPayload.options.testRuns.0.id', $testRun->id)
        ->where('initialPayload.options.environments.0', Issue::ENVIRONMENT_DEVELOPMENT)
        ->where('initialPayload.options.environments.1', Issue::ENVIRONMENT_SIT)
        ->where('initialPayload.options.defectTypes.0', Issue::DEFECT_TYPE_UI_UX)
        ->where('initialPayload.options.defectTypes.1', Issue::DEFECT_TYPE_FUNCTIONAL)
        ->where('initialPayload.data.0.test_run.name', 'Nightly Checkout Run'));
});

test('authenticated users can visit an issue detail page', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Billing Release Plan',
    ]);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'name' => 'Billing Smoke Run',
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);
    $issue = Issue::factory()->for($project, 'project')->create([
        'title' => 'Invoice preview fails for zero-value items',
        'description' => 'Open an invoice with zero-value line items and click preview.',
        'expected_result' => 'The preview renders all line items including zero values.',
        'actual_result' => 'The preview panel crashes and stays blank.',
        'priority' => Issue::PRIORITY_MEDIUM,
        'severity' => Issue::SEVERITY_HIGH,
        'status' => Issue::STATUS_IN_TESTING,
        'reporter' => 'Taylor Park',
        'assignee' => 'Sam Chen',
        'environment' => 'UAT',
        'defect_type' => 'Functional',
        'test_plan_id' => $testPlan->id,
        'test_run_id' => $testRun->id,
    ]);

    $response = $this->actingAs($user)->get(route('issues.show', $issue));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('issues/Show')
        ->where('initialIssue.id', $issue->id)
        ->where('initialIssue.title', 'Invoice preview fails for zero-value items')
        ->where('initialIssue.description', 'Open an invoice with zero-value line items and click preview.')
        ->where('initialIssue.expected_result', 'The preview renders all line items including zero values.')
        ->where('initialIssue.actual_result', 'The preview panel crashes and stays blank.')
        ->where('initialIssue.reporter', 'Taylor Park')
        ->where('initialIssue.assignee', 'Sam Chen')
        ->where('initialIssue.environment', 'UAT')
        ->where('initialIssue.defect_type', 'Functional')
        ->where('initialIssue.test_plan.name', 'Billing Release Plan')
        ->where('initialIssue.test_run.name', 'Billing Smoke Run'));
});

test('users can list only issues from the active project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $visibleIssue = Issue::factory()->for($project, 'project')->create([
        'title' => 'Visible issue',
        'description' => 'Visible issue',
        'status' => Issue::STATUS_OPEN,
    ]);

    $otherOwnedProject = Project::factory()->for($user)->create();
    Issue::factory()->for($otherOwnedProject, 'project')->create([
        'title' => 'Hidden from other owned project',
        'description' => 'Hidden from other owned project',
        'status' => Issue::STATUS_OPEN,
    ]);

    $otherUsersProject = Project::factory()->for(User::factory())->create();
    Issue::factory()->for($otherUsersProject, 'project')->create([
        'title' => 'Hidden from another user',
        'description' => 'Hidden from another user',
        'status' => Issue::STATUS_OPEN,
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->getJson(route('api.issues.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $visibleIssue->id);
    $response->assertJsonPath('stats.totalActiveIssues', 1);
});

test('users can filter issues by severity and status', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $matchingIssue = Issue::factory()->for($project, 'project')->create([
        'title' => 'Critical open issue',
        'description' => 'Critical open issue',
        'severity' => Issue::SEVERITY_CRITICAL,
        'status' => Issue::STATUS_OPEN,
    ]);

    Issue::factory()->for($project, 'project')->create([
        'severity' => Issue::SEVERITY_HIGH,
        'status' => Issue::STATUS_OPEN,
    ]);

    Issue::factory()->for($project, 'project')->create([
        'severity' => Issue::SEVERITY_CRITICAL,
        'status' => Issue::STATUS_SOLVED,
    ]);

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->getJson(route('api.issues.index', [
            'severity' => Issue::SEVERITY_CRITICAL,
            'status' => Issue::STATUS_OPEN,
        ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $matchingIssue->id);
});

test('users can create an issue in the active project with linked artifacts', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testRun = TestRun::factory()->for($testPlan)->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.issues.store'), [
            'title' => 'Session expires before checkout confirmation',
            'description' => 'Proceed through checkout and wait for confirmation.',
            'expected_result' => 'The checkout confirmation page finishes loading.',
            'actual_result' => 'The session expires before the page is rendered.',
            'priority' => Issue::PRIORITY_URGENT,
            'severity' => Issue::SEVERITY_CRITICAL,
            'status' => Issue::STATUS_OPEN,
            'reporter' => 'Nadia',
            'assignee' => 'Rian',
            'environment' => 'Staging',
            'defect_type' => 'Functional',
            'test_plan_id' => $testPlan->id,
            'test_run_id' => $testRun->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.title', 'Session expires before checkout confirmation');
    $response->assertJsonPath('data.description', 'Proceed through checkout and wait for confirmation.');
    $response->assertJsonPath('data.expected_result', 'The checkout confirmation page finishes loading.');
    $response->assertJsonPath('data.actual_result', 'The session expires before the page is rendered.');
    $response->assertJsonPath('data.project.id', $project->project_id);
    $response->assertJsonPath('data.priority', Issue::PRIORITY_URGENT);
    $response->assertJsonPath('data.test_plan.id', $testPlan->id);
    $response->assertJsonPath('data.test_run.id', $testRun->id);

    $this->assertDatabaseHas('issues', [
        'project_id' => $project->project_id,
        'test_plan_id' => $testPlan->id,
        'test_run_id' => $testRun->id,
        'title' => 'Session expires before checkout confirmation',
        'priority' => Issue::PRIORITY_URGENT,
        'reporter' => 'Nadia',
        'status' => Issue::STATUS_OPEN,
    ]);
});

test('users cannot create an issue without an active project selected', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.issues.store'), [
        'description' => 'Missing project context',
        'title' => 'Missing project context',
        'priority' => Issue::PRIORITY_LOW,
        'severity' => Issue::SEVERITY_LOW,
        'status' => Issue::STATUS_OPEN,
        'reporter' => 'No Session',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['project']);
});

test('users cannot link issues to test artifacts outside the active project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $otherPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $otherRun = TestRun::factory()->for($otherPlan)->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.issues.store'), [
            'description' => 'Foreign links should fail',
            'title' => 'Foreign links should fail',
            'priority' => Issue::PRIORITY_MEDIUM,
            'severity' => Issue::SEVERITY_HIGH,
            'status' => Issue::STATUS_OPEN,
            'reporter' => 'Ari',
            'test_plan_id' => $otherPlan->id,
            'test_run_id' => $otherRun->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'test_plan_id',
        'test_run_id',
    ]);
});

test('issue environment and defect type validation reject invalid values', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.issues.store'), [
            'description' => 'Invalid dropdown values should fail',
            'title' => 'Invalid dropdown values should fail',
            'priority' => Issue::PRIORITY_MEDIUM,
            'severity' => Issue::SEVERITY_HIGH,
            'status' => Issue::STATUS_OPEN,
            'reporter' => 'Ari',
            'environment' => 'QA Lab',
            'defect_type' => 'API',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'environment',
        'defect_type',
    ]);
});

test('issue closed_at is set when solving and cleared when reopening', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $issue = Issue::factory()->for($project, 'project')->create([
        'status' => Issue::STATUS_OPEN,
        'closed_at' => null,
    ]);

    $closedAt = Carbon::parse('2026-06-20 11:30:00');
    Carbon::setTestNow($closedAt);

    $this->actingAs($user)
        ->putJson(route('api.issues.update', $issue), [
            'title' => $issue->title,
            'description' => $issue->description,
            'expected_result' => $issue->expected_result,
            'actual_result' => $issue->actual_result,
            'priority' => $issue->priority,
            'severity' => $issue->severity,
            'status' => Issue::STATUS_SOLVED,
            'reporter' => $issue->reporter,
            'assignee' => $issue->assignee,
            'environment' => $issue->environment,
            'defect_type' => $issue->defect_type,
            'test_plan_id' => null,
            'test_run_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.closed_at', $closedAt->toIso8601String());

    $this->assertDatabaseHas('issues', [
        'id' => $issue->id,
        'status' => Issue::STATUS_SOLVED,
    ]);

    Carbon::setTestNow();

    $this->actingAs($user)
        ->putJson(route('api.issues.update', $issue), [
            'title' => $issue->title,
            'description' => $issue->description,
            'expected_result' => $issue->expected_result,
            'actual_result' => $issue->actual_result,
            'priority' => $issue->priority,
            'severity' => $issue->severity,
            'status' => Issue::STATUS_OPEN,
            'reporter' => $issue->reporter,
            'assignee' => $issue->assignee,
            'environment' => $issue->environment,
            'defect_type' => $issue->defect_type,
            'test_plan_id' => null,
            'test_run_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.closed_at', null);

    $this->assertDatabaseHas('issues', [
        'id' => $issue->id,
        'status' => Issue::STATUS_OPEN,
        'closed_at' => null,
    ]);
});

test('users can delete an issue', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $issue = Issue::factory()->for($project, 'project')->create();

    $response = $this->actingAs($user)->deleteJson(route('api.issues.destroy', $issue));

    $response->assertOk();
    $response->assertJsonPath('message', 'Issue deleted.');

    $this->assertSoftDeleted('issues', [
        'id' => $issue->id,
    ]);
});
