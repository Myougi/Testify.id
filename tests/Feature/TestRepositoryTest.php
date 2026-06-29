<?php

use App\Models\Project;
use App\Models\TestCase as RepositoryTestCase;
use App\Models\TestCaseStep;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\User;

test('guests are redirected from the test repository page', function (): void {
    $response = $this->get(route('test-repositories.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the test repository page', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Authentication Suite',
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Authentication',
        'key_prefix' => 'AUTH',
        'sort_order' => 1,
    ]);
    $childModule = TestRepositoryModule::factory()->for($project, 'project')->create([
        'parent_id' => $module->id,
        'name' => 'Login Flow',
        'key_prefix' => 'AUTH-LOGIN',
        'sort_order' => 2,
    ]);
    $testCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'case_key' => 'TC-AUTH-01',
            'title' => 'Verify valid login credentials',
            'automation_status' => RepositoryTestCase::AUTOMATION_AUTOMATED,
            'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
            'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
        ]);
    RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($childModule, 'module')
        ->create([
            'case_key' => 'TC-AUTH-LOGIN-01',
            'title' => 'Verify valid password reset request',
            'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
            'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
            'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
        ]);

    TestCaseStep::factory()->for($testCase)->create([
        'position' => 1,
        'action' => 'Enter valid credentials.',
        'expected_result' => 'User reaches the dashboard.',
    ]);

    $response = $this->actingAs($user)->get(route('test-repositories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-repositories/Index')
        ->has('initialPayload.data', 2)
        ->where('initialPayload.options.modules.0.name', 'Authentication')
        ->where('initialPayload.options.modules.0.test_cases_count', 1)
        ->where('initialPayload.options.modules.1.name', 'Login Flow')
        ->where('initialPayload.options.modules.1.test_cases_count', 1)
        ->where('initialPayload.stats.totalCases', 2)
        ->where('initialPayload.stats.automatedCases', 1));
});

test('repository page includes ai modal options for the active project only', function (): void {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create([
        'project_name' => 'Checkout Suite',
    ]);
    $otherProject = Project::factory()->for($user)->create([
        'project_name' => 'Billing Suite',
    ]);
    $activeModule = TestRepositoryModule::factory()->for($activeProject, 'project')->create([
        'name' => 'Checkout',
    ]);
    TestRepositoryModule::factory()->for($otherProject, 'project')->create([
        'name' => 'Billing',
    ]);
    $activePlan = TestPlan::factory()->for($activeProject, 'project')->create([
        'name' => 'Checkout Plan',
    ]);
    TestPlan::factory()->for($otherProject, 'project')->create([
        'name' => 'Billing Plan',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->get(route('test-repositories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-repositories/Index')
        ->where('initialPayload.options.aiGeneration.hasActiveProject', true)
        ->where('initialPayload.options.aiGeneration.activeProject.id', $activeProject->project_id)
        ->where('initialPayload.options.aiGeneration.activeProject.name', 'Checkout Suite')
        ->has('initialPayload.options.aiGeneration.testPlans', 1)
        ->where('initialPayload.options.aiGeneration.testPlans.0.id', $activePlan->id)
        ->has('initialPayload.options.aiGeneration.modules', 1)
        ->where('initialPayload.options.aiGeneration.modules.0.id', $activeModule->id));
});

test('repository page returns disabled ai options when no active project is selected', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    TestRepositoryModule::factory()->for($project, 'project')->create();
    TestPlan::factory()->for($project, 'project')->create();

    $response = $this->actingAs($user)->get(route('test-repositories.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('test-repositories/Index')
        ->where('initialPayload.options.aiGeneration.hasActiveProject', false)
        ->where('initialPayload.options.aiGeneration.activeProject', null)
        ->where('initialPayload.options.aiGeneration.testPlans', [])
        ->where('initialPayload.options.aiGeneration.modules', []));
});

test('repository api returns ai modal options for the active project only', function (): void {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $activeModule = TestRepositoryModule::factory()->for($activeProject, 'project')->create([
        'name' => 'Checkout',
    ]);
    TestRepositoryModule::factory()->for($otherProject, 'project')->create([
        'name' => 'Billing',
    ]);
    $activePlan = TestPlan::factory()->for($activeProject, 'project')->create([
        'name' => 'Checkout Plan',
    ]);
    TestPlan::factory()->for($otherProject, 'project')->create([
        'name' => 'Billing Plan',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->getJson(route('api.test-cases.index'));

    $response->assertOk();
    $response->assertJsonPath('options.aiGeneration.hasActiveProject', true);
    $response->assertJsonPath('options.aiGeneration.activeProject.id', $activeProject->project_id);
    $response->assertJsonPath('options.aiGeneration.testPlans.0.id', $activePlan->id);
    $response->assertJsonPath('options.aiGeneration.modules.0.id', $activeModule->id);
    expect($response->json('options.aiGeneration.testPlans'))->toHaveCount(1);
    expect($response->json('options.aiGeneration.modules'))->toHaveCount(1);
});

test('users can manage repository folders in their own projects', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $createResponse = $this->actingAs($user)->postJson(route('api.test-repository-modules.store'), [
        'project_id' => $project->project_id,
        'parent_id' => null,
        'name' => 'Authentication',
        'key_prefix' => 'auth',
    ]);

    $createResponse->assertCreated();
    $createResponse->assertJsonPath('data.name', 'Authentication');
    $createResponse->assertJsonPath('data.key_prefix', 'AUTH');

    $module = TestRepositoryModule::query()->firstOrFail();

    $updateResponse = $this->actingAs($user)->putJson(route('api.test-repository-modules.update', [
        'module' => $module,
    ]), [
        'project_id' => $project->project_id,
        'parent_id' => null,
        'name' => 'Login Flow',
        'key_prefix' => 'LOGIN',
    ]);

    $updateResponse->assertOk();
    $updateResponse->assertJsonPath('data.name', 'Login Flow');

    $deleteResponse = $this->actingAs($user)->deleteJson(route('api.test-repository-modules.destroy', [
        'module' => $module,
    ]));

    $deleteResponse->assertOk();

    $this->assertSoftDeleted('test_repository_modules', [
        'id' => $module->id,
    ]);
});

test('repository folders cannot be deleted while they contain child folders or cases', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $parent = TestRepositoryModule::factory()->for($project, 'project')->create();
    TestRepositoryModule::factory()->for($project, 'project')->create([
        'parent_id' => $parent->id,
    ]);

    $childResponse = $this->actingAs($user)->deleteJson(route('api.test-repository-modules.destroy', [
        'module' => $parent,
    ]));

    $childResponse->assertStatus(422);

    $leaf = TestRepositoryModule::factory()->for($project, 'project')->create();
    RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($leaf, 'module')
        ->create();

    $caseResponse = $this->actingAs($user)->deleteJson(route('api.test-repository-modules.destroy', [
        'module' => $leaf,
    ]));

    $caseResponse->assertStatus(422);
});

test('users can list only repository test cases from their own projects', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $visibleCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'title' => 'Visible checkout case',
        ]);

    $otherProject = Project::factory()->for(User::factory())->create();
    $otherModule = TestRepositoryModule::factory()->for($otherProject, 'project')->create();
    RepositoryTestCase::factory()
        ->for($otherProject, 'project')
        ->for($otherModule, 'module')
        ->create([
            'title' => 'Hidden checkout case',
        ]);

    $response = $this->actingAs($user)->getJson(route('api.test-cases.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $visibleCase->id);
});

test('users can filter repository test cases by module descendants and test type', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $parent = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Authentication',
    ]);
    $child = TestRepositoryModule::factory()->for($project, 'project')->create([
        'parent_id' => $parent->id,
        'name' => 'OAuth 2.0',
    ]);
    $matchingCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($child, 'module')
        ->create([
            'test_type' => RepositoryTestCase::TYPE_SECURITY,
        ]);
    $parentCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($parent, 'module')
        ->create([
            'test_type' => RepositoryTestCase::TYPE_SECURITY,
        ]);
    $otherModule = TestRepositoryModule::factory()->for($project, 'project')->create();
    RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($otherModule, 'module')
        ->create([
            'test_type' => RepositoryTestCase::TYPE_SECURITY,
        ]);
    RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($child, 'module')
        ->create([
            'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
        ]);

    $response = $this->actingAs($user)->getJson(route('api.test-cases.index', [
        'module_id' => $parent->id,
        'test_type' => RepositoryTestCase::TYPE_SECURITY,
    ]));

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['id' => $matchingCase->id]);
    $response->assertJsonFragment(['id' => $parentCase->id]);
});

test('users can create a repository test case with generated key and ordered steps', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'key_prefix' => 'AUTH',
    ]);

    RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'case_key' => 'TC-AUTH-01',
        ]);

    $response = $this->actingAs($user)->postJson(route('api.test-cases.store'), [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'title' => 'Password reset email delivery',
        'priority' => RepositoryTestCase::PRIORITY_MEDIUM,
        'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
        'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
        'test_type' => RepositoryTestCase::TYPE_ACCEPTANCE,
        'preconditions' => 'A registered user exists.',
        'steps' => [
            [
                'action' => 'Request a password reset.',
                'expected_result' => 'A reset email is queued.',
            ],
            [
                'action' => 'Open the reset link.',
                'expected_result' => 'The reset form is displayed.',
            ],
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.case_key', 'TC-AUTH-02');
    $response->assertJsonPath('data.steps.0.position', 1);
    $response->assertJsonPath('data.steps.1.position', 2);

    $this->assertDatabaseHas('test_cases', [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'case_key' => 'TC-AUTH-02',
        'title' => 'Password reset email delivery',
        'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
        'test_type' => RepositoryTestCase::TYPE_ACCEPTANCE,
    ]);
});

test('users can create a repository test case directly on a parent folder', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $parent = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Authentication',
        'key_prefix' => 'AUTH',
        'sort_order' => 1,
    ]);
    TestRepositoryModule::factory()->for($project, 'project')->create([
        'parent_id' => $parent->id,
        'name' => 'OAuth 2.0',
        'key_prefix' => 'OAUTH',
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($user)->postJson(route('api.test-cases.store'), [
        'project_id' => $project->project_id,
        'module_id' => $parent->id,
        'title' => 'Parent folder case',
        'priority' => RepositoryTestCase::PRIORITY_HIGH,
        'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
        'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
        'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
        'preconditions' => null,
        'steps' => [],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.module_id', $parent->id);
    $response->assertJsonPath('data.case_key', 'TC-AUTH-01');

    $pageResponse = $this->actingAs($user)->get(route('test-repositories.index'));

    $pageResponse->assertOk();
    $pageResponse->assertInertia(fn ($page) => $page
        ->where('initialPayload.options.modules.0.name', 'Authentication')
        ->where('initialPayload.options.modules.0.test_cases_count', 1)
        ->where('initialPayload.options.modules.1.name', 'OAuth 2.0')
        ->where('initialPayload.options.modules.1.test_cases_count', 0));
});

test('users can create repository test cases without steps', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();

    $response = $this->actingAs($user)->postJson(route('api.test-cases.store'), [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'title' => 'Case without steps',
        'priority' => RepositoryTestCase::PRIORITY_HIGH,
        'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
        'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
        'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
        'preconditions' => null,
        'steps' => [],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.title', 'Case without steps');
    $response->assertJsonCount(0, 'data.steps');
    $this->assertDatabaseCount('test_case_steps', 0);
});

test('users cannot create repository test cases with invalid enum values', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();

    $response = $this->actingAs($user)->postJson(route('api.test-cases.store'), [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'title' => 'Invalid case',
        'priority' => 'P0',
        'automation_status' => 'Scripted',
        'test_behavior' => 'Neutral',
        'test_type' => 'Compatibility',
        'steps' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'priority',
        'automation_status',
        'test_behavior',
    ]);
});

test('users cannot create repository test cases for another users project or folder', function (): void {
    $user = User::factory()->create();
    $otherProject = Project::factory()->for(User::factory())->create();
    $otherModule = TestRepositoryModule::factory()->for($otherProject, 'project')->create();

    $response = $this->actingAs($user)->postJson(route('api.test-cases.store'), [
        'project_id' => $otherProject->project_id,
        'module_id' => $otherModule->id,
        'title' => 'Unauthorized case',
        'priority' => RepositoryTestCase::PRIORITY_HIGH,
        'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
        'test_behavior' => RepositoryTestCase::BEHAVIOR_NEGATIVE,
        'test_type' => RepositoryTestCase::TYPE_SECURITY,
        'steps' => [
            [
                'action' => 'Try to save.',
                'expected_result' => 'Validation fails.',
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['project_id']);
});

test('bulk ai case creation requires an active project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();

    $response = $this->actingAs($user)->postJson(route('api.test-cases.bulk-store'), [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'test_plan_id' => $testPlan->id,
        'cases' => [
            [
                'title' => 'Generated case',
                'priority' => RepositoryTestCase::PRIORITY_MEDIUM,
                'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
                'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
                'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
                'preconditions' => null,
                'steps' => [],
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['project']);
});

test('bulk ai case creation rejects foreign active project artifacts', function (): void {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $foreignModule = TestRepositoryModule::factory()->for($otherProject, 'project')->create();
    $foreignPlan = TestPlan::factory()->for($otherProject, 'project')->create();

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->postJson(route('api.test-cases.bulk-store'), [
            'project_id' => $activeProject->project_id,
            'module_id' => $foreignModule->id,
            'test_plan_id' => $foreignPlan->id,
            'cases' => [
                [
                    'title' => 'Generated case',
                    'priority' => RepositoryTestCase::PRIORITY_MEDIUM,
                    'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
                    'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
                    'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
                    'preconditions' => null,
                    'steps' => [],
                ],
            ],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'module_id',
        'test_plan_id',
    ]);
});

test('bulk ai case creation creates multiple repository test cases', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Checkout',
        'key_prefix' => 'CHK',
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Checkout Plan',
    ]);

    RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'case_key' => 'TC-CHK-01',
        ]);

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.test-cases.bulk-store'), [
            'project_id' => $project->project_id,
            'module_id' => $module->id,
            'test_plan_id' => $testPlan->id,
            'cases' => [
                [
                    'title' => 'Generated checkout pass path',
                    'priority' => RepositoryTestCase::PRIORITY_MEDIUM,
                    'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
                    'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
                    'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
                    'preconditions' => 'User has an active cart.',
                    'steps' => [
                        [
                            'action' => 'Open checkout with a valid cart.',
                            'expected_result' => 'Checkout loads successfully.',
                        ],
                    ],
                ],
                [
                    'title' => 'Generated checkout fail path',
                    'priority' => RepositoryTestCase::PRIORITY_HIGH,
                    'automation_status' => RepositoryTestCase::AUTOMATION_AUTOMATED,
                    'test_behavior' => RepositoryTestCase::BEHAVIOR_NEGATIVE,
                    'test_type' => RepositoryTestCase::TYPE_REGRESSION,
                    'preconditions' => null,
                    'steps' => [
                        [
                            'action' => 'Submit an expired card.',
                            'expected_result' => 'The payment is rejected.',
                        ],
                    ],
                ],
            ],
        ]);

    $response->assertCreated();
    $response->assertJsonPath('meta.created', 2);
    $response->assertJsonPath('meta.test_plan.id', $testPlan->id);
    $response->assertJsonPath('meta.module.id', $module->id);
    $response->assertJsonPath('data.0.case_key', 'TC-CHK-02');
    $response->assertJsonPath('data.1.case_key', 'TC-CHK-03');
    $response->assertJsonPath('data.0.steps.0.position', 1);
    $response->assertJsonPath('data.1.steps.0.position', 1);

    $this->assertDatabaseHas('test_cases', [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'case_key' => 'TC-CHK-02',
        'title' => 'Generated checkout pass path',
    ]);
    $this->assertDatabaseHas('test_cases', [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'case_key' => 'TC-CHK-03',
        'title' => 'Generated checkout fail path',
    ]);
});

test('bulk ai case creation does not create partial data when one case is invalid', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'key_prefix' => 'AUTH',
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create();

    $response = $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.test-cases.bulk-store'), [
            'project_id' => $project->project_id,
            'module_id' => $module->id,
            'test_plan_id' => $testPlan->id,
            'cases' => [
                [
                    'title' => 'Valid generated case',
                    'priority' => RepositoryTestCase::PRIORITY_MEDIUM,
                    'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
                    'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
                    'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
                    'preconditions' => null,
                    'steps' => [],
                ],
                [
                    'title' => 'Invalid generated case',
                    'priority' => 'P0',
                    'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
                    'test_behavior' => RepositoryTestCase::BEHAVIOR_POSITIVE,
                    'test_type' => RepositoryTestCase::TYPE_FUNCTIONAL,
                    'preconditions' => null,
                    'steps' => [],
                ],
            ],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['cases.1.priority']);
    $this->assertDatabaseCount('test_cases', 0);
});

test('users can update repository test cases and replace steps', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'title' => 'Original case',
        ]);

    TestCaseStep::factory()->for($testCase)->create([
        'position' => 1,
    ]);

    $response = $this->actingAs($user)->putJson(route('api.test-cases.update', [
        'testCase' => $testCase,
    ]), [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'title' => 'Updated case',
        'priority' => RepositoryTestCase::PRIORITY_LOW,
        'automation_status' => RepositoryTestCase::AUTOMATION_AUTOMATED,
        'test_behavior' => RepositoryTestCase::BEHAVIOR_NEGATIVE,
        'test_type' => RepositoryTestCase::TYPE_REGRESSION,
        'preconditions' => null,
        'steps' => [
            [
                'action' => 'Run the regression script.',
                'expected_result' => 'The failure state is reported.',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'Updated case');
    $response->assertJsonPath('data.steps.0.action', 'Run the regression script.');

    $this->assertDatabaseHas('test_cases', [
        'id' => $testCase->id,
        'title' => 'Updated case',
        'test_behavior' => RepositoryTestCase::BEHAVIOR_NEGATIVE,
    ]);
    $this->assertDatabaseCount('test_case_steps', 1);
});

test('users cannot update repository test cases in other users projects', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for(User::factory())->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create();

    $response = $this->actingAs($user)->putJson(route('api.test-cases.update', [
        'testCase' => $testCase,
    ]), [
        'project_id' => $project->project_id,
        'module_id' => $module->id,
        'title' => 'Unauthorized update',
        'priority' => RepositoryTestCase::PRIORITY_HIGH,
        'automation_status' => RepositoryTestCase::AUTOMATION_MANUAL,
        'test_behavior' => RepositoryTestCase::BEHAVIOR_NEGATIVE,
        'test_type' => RepositoryTestCase::TYPE_SECURITY,
        'steps' => [
            [
                'action' => 'Try to save.',
                'expected_result' => 'Authorization fails.',
            ],
        ],
    ]);

    $response->assertForbidden();
});

test('users can delete repository test cases in their own projects', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testCase = RepositoryTestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create();

    TestCaseStep::factory()->for($testCase)->create([
        'position' => 1,
    ]);

    $response = $this->actingAs($user)->deleteJson(route('api.test-cases.destroy', [
        'testCase' => $testCase,
    ]));

    $response->assertOk();
    $response->assertJsonPath('message', 'Test case deleted.');
    $this->assertSoftDeleted('test_cases', [
        'id' => $testCase->id,
    ]);
    $this->assertDatabaseHas('test_case_steps', [
        'test_case_id' => $testCase->id,
    ]);
});
