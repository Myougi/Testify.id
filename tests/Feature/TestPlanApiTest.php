<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected from the test plans page', function () {
    $response = $this->get(route('test-plans.index'));

    $response->assertRedirect(route('login'));
});

test('guests are redirected when exporting a test plan pdf', function () {
    $testPlan = TestPlan::factory()->create();

    $this->get(route('test-plans.export', $testPlan))
        ->assertRedirect(route('login'));
});

test('users can export their test plan as a pdf', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-28 10:30:00'));

    try {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'project_name' => 'Helios Cloud',
        ]);
        $module = TestRepositoryModule::factory()->create([
            'project_id' => $project->project_id,
            'name' => 'Authentication',
        ]);
        $testPlan = TestPlan::factory()->for($project, 'project')->create([
            'name' => 'Release Readiness Plan',
            'description' => 'Validate the release candidate.',
            'scope' => 'Authentication and account recovery.',
            'strategy' => 'Risk-based functional testing.',
            'criteria' => 'All critical scenarios must pass.',
            'tester' => 'Sarah Chen',
            'release_version' => 'v2.4.0',
        ]);
        $assignedCase = TestCase::factory()->create([
            'project_id' => $project->project_id,
            'module_id' => $module->id,
            'case_key' => 'AUTH-001',
            'title' => 'Valid user signs in',
        ]);

        $assignedCase->steps()->createMany([
            [
                'position' => 1,
                'action' => 'Enter valid credentials.',
                'expected_result' => 'The dashboard is displayed.',
            ],
            [
                'position' => 2,
                'action' => 'Refresh the page.',
                'expected_result' => 'The authenticated session remains active.',
            ],
        ]);
        $testPlan->testCases()->attach($assignedCase);

        $response = $this->actingAs($user)
            ->get(route('test-plans.export', $testPlan));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('testify-helios-cloud-release-readiness-plan-2026-06-28.pdf');

        expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

test('users cannot export test plans owned by another user', function () {
    $user = User::factory()->create();
    $testPlan = TestPlan::factory()->create();

    $this->actingAs($user)
        ->get(route('test-plans.export', $testPlan))
        ->assertForbidden();
});

test('users can export a test plan without optional content or assigned cases', function () {
    $user = User::factory()->create();
    $testPlan = TestPlan::factory()
        ->for(Project::factory()->for($user), 'project')
        ->create([
            'description' => null,
            'scope' => null,
            'strategy' => null,
            'criteria' => null,
        ]);

    $response = $this->actingAs($user)
        ->get(route('test-plans.export', $testPlan));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();
});

test('authenticated users can visit the test plans page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Helios Cloud',
    ]);

    TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'name' => 'Core API V2.4 Security Audit',
            'status' => TestPlan::STATUS_IN_PROGRESS,
            'due_date' => Carbon::today()->subDay(),
            'tester' => 'Alex Rivera',
        ]);

    $response = $this->actingAs($user)->get(route('test-plans.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-plans/Index')
        ->has('initialPayload.data', 1)
        ->where('initialPayload.data.0.name', 'Core API V2.4 Security Audit')
        ->where('initialPayload.data.0.project.name', 'Helios Cloud')
        ->where('initialPayload.data.0.tester', 'Alex Rivera')
        ->where('initialPayload.stats.totalPlans', 1)
        ->where('initialPayload.stats.activePlans', 1)
        ->where('initialPayload.stats.completedPlans', 0)
        ->where('initialPayload.stats.overduePlans', 1));
});

test('authenticated users can visit the test plan detail page with assigned and available cases', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Xendit Payment Gateway',
    ]);
    $module = TestRepositoryModule::factory()->create([
        'project_id' => $project->project_id,
        'name' => 'Checkout',
        'key_prefix' => 'CHK',
    ]);
    $testPlan = TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'name' => 'Release Readiness Plan',
        ]);
    $assignedCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'case_key' => 'CHK-001',
        'title' => 'Valid checkout succeeds',
    ]);
    $availableCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'case_key' => 'CHK-002',
        'title' => 'Declined payment shows recovery state',
    ]);

    $testPlan->testCases()->attach($assignedCase);

    $response = $this->actingAs($user)->get(route('test-plans.show', $testPlan));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-plans/Show')
        ->where('initialTestPlan.id', $testPlan->id)
        ->where('initialTestPlan.assigned_test_cases_count', 1)
        ->where('initialTestPlan.test_cases.0.case_key', 'CHK-001')
        ->where('initialAvailableCases.data.0.case_key', 'CHK-002')
        ->where('initialAvailableCases.options.modules.0.name', 'Checkout'));
});

test('test plan detail page lists only test runs for the current plan', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $currentPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Current Release Plan',
    ]);
    $otherPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Other Release Plan',
    ]);
    $currentRun = TestRun::factory()->for($currentPlan)->create([
        'name' => 'Current plan smoke run',
        'status' => TestRun::STATUS_IN_PROGRESS,
    ]);

    TestRun::factory()->for($otherPlan)->create([
        'name' => 'Other plan run',
    ]);

    $response = $this->actingAs($user)->get(route('test-plans.show', $currentPlan));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-plans/Show')
        ->has('initialTestRuns.data', 1)
        ->where('initialTestRuns.data.0.id', $currentRun->id)
        ->where('initialTestRuns.data.0.name', 'Current plan smoke run')
        ->where('initialTestRuns.data.0.status', TestRun::STATUS_IN_PROGRESS)
        ->where('initialTestRuns.meta.total', 1));
});

test('test plans page can lock the selected project from the query string', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Apollo E-commerce',
    ]);

    $response = $this->actingAs($user)->get(route('test-plans.index', [
        'project_id' => $project->project_id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-plans/Index')
        ->where('initialPayload.options.selectedProjectId', $project->project_id));
});

test('test plans page only displays plans from the active project', function () {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create([
        'project_name' => 'Visible project',
    ]);
    $otherProject = Project::factory()->for($user)->create([
        'project_name' => 'Hidden project',
    ]);
    $visiblePlan = TestPlan::factory()->for($activeProject, 'project')->create([
        'name' => 'Visible plan',
    ]);
    TestPlan::factory()->for($otherProject, 'project')->create([
        'name' => 'Hidden plan',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->get(route('test-plans.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-plans/Index')
        ->has('initialPayload.data', 1)
        ->where('initialPayload.data.0.id', $visiblePlan->id)
        ->where('initialPayload.stats.totalPlans', 1)
        ->where('initialPayload.options.selectedProjectId', $activeProject->project_id));
});

test('test plan detail rejects sibling-project plans while another project is active', function () {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $foreignPlan = TestPlan::factory()->for($otherProject, 'project')->create();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->get(route('test-plans.show', $foreignPlan))
        ->assertNotFound();
});

test('users can list only test plans from their own projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Payments Suite',
    ]);

    $visiblePlan = TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'name' => 'Payment Gateway Integration',
            'status' => TestPlan::STATUS_COMPLETED,
            'tester' => 'Sarah Chen',
        ]);

    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();

    TestPlan::factory()
        ->for($otherProject, 'project')
        ->create([
            'name' => 'Hidden Plan',
        ]);

    $response = $this->actingAs($user)->getJson(route('api.test-plans.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $visiblePlan->id);
    $response->assertJsonPath('data.0.tester', 'Sarah Chen');
    $response->assertJsonPath('stats.totalPlans', 1);
    $response->assertJsonPath('stats.completedPlans', 1);
});

test('test plan api only lists plans from the active project', function () {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $visiblePlan = TestPlan::factory()->for($activeProject, 'project')->create();
    TestPlan::factory()->for($otherProject, 'project')->create();

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->getJson(route('api.test-plans.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $visiblePlan->id);
    $response->assertJsonPath('stats.totalPlans', 1);
});

test('users can filter test plans by status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $matchingPlan = TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'name' => 'Legacy System Migration',
            'status' => TestPlan::STATUS_DRAFT,
            'tester' => 'Marko Vucic',
        ]);

    TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'status' => TestPlan::STATUS_COMPLETED,
            'tester' => 'Taylor Park',
        ]);

    $response = $this->actingAs($user)->getJson(route('api.test-plans.index', [
        'status' => TestPlan::STATUS_DRAFT,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $matchingPlan->id);
});

test('users can filter test plans by priority', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $matchingPlan = TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'name' => 'Urgent billing patch verification',
            'priority' => TestPlan::PRIORITY_URGENT,
        ]);

    TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'priority' => TestPlan::PRIORITY_LOW,
        ]);

    $response = $this->actingAs($user)->getJson(route('api.test-plans.index', [
        'priority' => TestPlan::PRIORITY_URGENT,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $matchingPlan->id);
    $response->assertJsonPath('data.0.priority', TestPlan::PRIORITY_URGENT);
});

test('users can create a test plan for their own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this->actingAs($user)->postJson(route('api.test-plans.store'), [
        'project_id' => $project->project_id,
        'name' => 'Mobile App Checkout Regression',
        'description' => 'Regression coverage for checkout and payment flows.',
        'status' => TestPlan::STATUS_IN_PROGRESS,
        'priority' => TestPlan::PRIORITY_HIGH,
        'start_date' => '2026-06-10',
        'due_date' => '2026-06-20',
        'release_version' => 'v2.4.0-beta',
        'tester' => 'qa.lead@example.com',
        'scope' => 'Checkout, cart, and promo code validation.',
        'strategy' => 'Blend API checks with focused end-to-end smoke tests.',
        'criteria' => 'All P1 scenarios pass with no open critical defects.',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Mobile App Checkout Regression');
    $response->assertJsonPath('data.project.id', $project->project_id);
    $response->assertJsonPath('data.priority', TestPlan::PRIORITY_HIGH);
    $response->assertJsonPath('data.release_version', 'v2.4.0-beta');
    $response->assertJsonPath('data.tester', 'qa.lead@example.com');

    $this->assertDatabaseHas('test_plans', [
        'project_id' => $project->project_id,
        'name' => 'Mobile App Checkout Regression',
        'status' => TestPlan::STATUS_IN_PROGRESS,
        'priority' => TestPlan::PRIORITY_HIGH,
        'tester' => 'qa.lead@example.com',
    ]);
});

test('users cannot create a test plan for another users project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.test-plans.store'), [
        'project_id' => $project->project_id,
        'name' => 'Unauthorized Plan',
        'status' => TestPlan::STATUS_DRAFT,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['project_id']);
});

test('users can update test plans in their own projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()
        ->for($project, 'project')
        ->create([
            'status' => TestPlan::STATUS_DRAFT,
        ]);

    $response = $this->actingAs($user)->putJson(
        route('api.test-plans.update', $testPlan),
        [
            'project_id' => $project->project_id,
            'name' => 'Updated Release Readiness Plan',
            'description' => null,
            'status' => TestPlan::STATUS_COMPLETED,
            'priority' => TestPlan::PRIORITY_URGENT,
            'start_date' => '2026-06-12',
            'due_date' => '2026-06-18',
            'release_version' => 'v2.5.0',
            'tester' => 'Taylor Park',
            'scope' => 'Authentication and account settings coverage.',
            'strategy' => 'Manual exploratory pass plus smoke automation.',
            'criteria' => 'No blocker or critical issues remain open.',
        ],
    );

    $response->assertOk();
    $response->assertJsonPath('data.status', TestPlan::STATUS_COMPLETED);
    $response->assertJsonPath('data.priority', TestPlan::PRIORITY_URGENT);

    $this->assertDatabaseHas('test_plans', [
        'id' => $testPlan->id,
        'name' => 'Updated Release Readiness Plan',
        'status' => TestPlan::STATUS_COMPLETED,
        'priority' => TestPlan::PRIORITY_URGENT,
        'tester' => 'Taylor Park',
    ]);
});

test('test plan api show includes assigned cases and count', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->create([
        'project_id' => $project->project_id,
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $assignedCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'case_key' => 'API-001',
        'title' => 'API smoke test',
    ]);

    $testPlan->testCases()->attach($assignedCase);

    $response = $this->actingAs($user)->getJson(route('api.test-plans.show', $testPlan));

    $response->assertOk();
    $response->assertJsonPath('data.id', $testPlan->id);
    $response->assertJsonPath('data.assigned_test_cases_count', 1);
    $response->assertJsonPath('data.test_cases.0.case_key', 'API-001');
    $response->assertJsonPath('data.test_cases.0.module.id', $module->id);
});

test('available test cases endpoint excludes already assigned cases and supports filters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $checkoutModule = TestRepositoryModule::factory()->create([
        'project_id' => $project->project_id,
        'name' => 'Checkout',
        'sort_order' => 1,
    ]);
    $paymentsModule = TestRepositoryModule::factory()->create([
        'project_id' => $project->project_id,
        'name' => 'Payments',
        'sort_order' => 2,
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $assignedCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $checkoutModule->id,
        'case_key' => 'CHK-001',
        'title' => 'Assigned checkout case',
    ]);
    $matchingCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $checkoutModule->id,
        'case_key' => 'CHK-002',
        'title' => 'Available checkout case',
        'priority' => TestCase::PRIORITY_HIGH,
        'automation_status' => TestCase::AUTOMATION_AUTOMATED,
        'test_behavior' => TestCase::BEHAVIOR_POSITIVE,
        'test_type' => TestCase::TYPE_REGRESSION,
    ]);
    TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $paymentsModule->id,
        'case_key' => 'PAY-001',
        'title' => 'Other module case',
        'priority' => TestCase::PRIORITY_MEDIUM,
        'automation_status' => TestCase::AUTOMATION_MANUAL,
        'test_behavior' => TestCase::BEHAVIOR_NEGATIVE,
        'test_type' => TestCase::TYPE_SECURITY,
    ]);

    $testPlan->testCases()->attach($assignedCase);

    $response = $this->actingAs($user)->getJson(route('api.test-plans.available-test-cases', [
        'test_plan' => $testPlan->id,
        'module_id' => $checkoutModule->id,
        'priority' => TestCase::PRIORITY_HIGH,
        'automation_status' => TestCase::AUTOMATION_AUTOMATED,
        'test_behavior' => TestCase::BEHAVIOR_POSITIVE,
        'test_type' => TestCase::TYPE_REGRESSION,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $matchingCase->id);
    $response->assertJsonPath('data.0.case_key', 'CHK-002');
    $response->assertJsonPath('filters.module_id', $checkoutModule->id);
});

test('users can bulk attach test cases to their test plan without duplicates across requests', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->create([
        'project_id' => $project->project_id,
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $firstCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $module->id,
    ]);
    $secondCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $module->id,
    ]);

    $firstResponse = $this->actingAs($user)->postJson(route('api.test-plans.attach-test-cases', [
        'test_plan' => $testPlan->id,
    ]), [
        'test_case_ids' => [$firstCase->id, $secondCase->id],
    ]);

    $firstResponse->assertOk();
    $firstResponse->assertJsonPath('message', 'Test cases added to the plan.');
    $firstResponse->assertJsonPath('data.assigned_test_cases_count', 2);

    $secondResponse = $this->actingAs($user)->postJson(route('api.test-plans.attach-test-cases', [
        'test_plan' => $testPlan->id,
    ]), [
        'test_case_ids' => [$firstCase->id],
    ]);

    $secondResponse->assertOk();
    $secondResponse->assertJsonPath('data.assigned_test_cases_count', 2);

    $attachedCaseIds = $testPlan->fresh()->testCases()->pluck('test_cases.id')->all();

    expect($attachedCaseIds)
        ->toHaveCount(2)
        ->toContain($firstCase->id, $secondCase->id);
});

test('users cannot attach test cases from another project to a test plan', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->create([
        'project_id' => $otherProject->project_id,
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $foreignCase = TestCase::factory()->create([
        'project_id' => $otherProject->project_id,
        'module_id' => $module->id,
    ]);

    $response = $this->actingAs($user)->postJson(route('api.test-plans.attach-test-cases', [
        'test_plan' => $testPlan->id,
    ]), [
        'test_case_ids' => [$foreignCase->id],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['test_case_ids']);
});

test('users can detach an assigned test case from a test plan', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->create([
        'project_id' => $project->project_id,
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $assignedCase = TestCase::factory()->create([
        'project_id' => $project->project_id,
        'module_id' => $module->id,
    ]);

    $testPlan->testCases()->attach($assignedCase);

    $response = $this->actingAs($user)->deleteJson(route('api.test-plans.detach-test-case', [
        'test_plan' => $testPlan->id,
        'testCase' => $assignedCase->id,
    ]));

    $response->assertOk();
    $response->assertJsonPath('message', 'Test case removed from the plan.');
    $response->assertJsonPath('data.assigned_test_cases_count', 0);

    $this->assertDatabaseMissing('test_case_test_plan', [
        'test_plan_id' => $testPlan->id,
        'test_case_id' => $assignedCase->id,
    ]);
});

test('users cannot update test plans in other users projects', function () {
    $user = User::factory()->create();
    $testPlan = TestPlan::factory()->create();

    $response = $this->actingAs($user)->putJson(
        route('api.test-plans.update', $testPlan),
        [
            'project_id' => $testPlan->project_id,
            'name' => 'Unauthorized Update',
            'status' => TestPlan::STATUS_DRAFT,
        ],
    );

    $response->assertForbidden();
});

test('users can delete test plans in their own projects', function () {
    $user = User::factory()->create();
    $testPlan = TestPlan::factory()
        ->for(Project::factory()->for($user), 'project')
        ->create();

    $response = $this->actingAs($user)->deleteJson(
        route('api.test-plans.destroy', $testPlan),
    );

    $response->assertOk();
    $response->assertJsonPath('message', 'Test plan deleted.');

    $this->assertSoftDeleted('test_plans', [
        'id' => $testPlan->id,
    ]);
});
