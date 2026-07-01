<?php

use App\Ai\Agents\SeniorQualityAssuranceAgent;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\TestCaseGenerationCountResolver;

test('guests are redirected from the ai generation endpoint', function (): void {
    $this->postJson(route('api.ai.generate'), [
        'artifact_type' => 'test_plan',
        'prompt' => 'Generate a release readiness plan.',
    ])->assertUnauthorized();
});

test('test plan draft generation uses the requested project and does not persist records', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Atlas Commerce',
    ]);

    SeniorQualityAssuranceAgent::fake([
        [
            'draft' => [
                'name' => 'Atlas checkout readiness',
                'description' => 'Validate the main checkout path before release.',
                'scope' => 'Checkout, taxes, and payment confirmation.',
                'strategy' => 'Mix smoke automation with exploratory payment testing.',
                'criteria' => 'All high-risk scenarios pass before signoff.',
                'priority' => 'P0',
                'start_date' => '2026-06-30',
                'due_date' => '2026-06-29',
                'release_version' => 'v3.2.0',
                'tester' => 'qa.lead@example.com',
            ],
            'warnings' => ['Missing browser coverage assumptions were inferred.'],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'test_plan',
            'project_id' => $project->project_id,
            'prompt' => 'Generate a test plan for the checkout release.',
        ])
        ->assertOk()
        ->assertJsonPath('draft.name', 'Atlas checkout readiness')
        ->assertJsonPath('draft.status', TestPlan::STATUS_DRAFT)
        ->assertJsonPath('draft.priority', TestPlan::PRIORITY_MEDIUM)
        ->assertJsonPath('draft.due_date', null)
        ->assertJsonPath('source_context.project.id', $project->project_id)
        ->assertJsonFragment([
            'Missing browser coverage assumptions were inferred.',
        ])
        ->assertJsonFragment([
            'Priority was reset to Medium.',
        ])
        ->assertJsonFragment([
            'Due date was cleared because it was earlier than the start date.',
        ]);

    SeniorQualityAssuranceAgent::assertPrompted(function ($prompt) use ($project): bool {
        return $prompt->agent->artifactType === 'test_plan'
            && $prompt->agent->requestedCount === 1
            && $prompt->agent->context['project']['id'] === $project->project_id
            && $prompt->prompt === 'Generate a test plan for the checkout release.';
    });

    $this->assertDatabaseCount('test_plans', 0);
    $this->assertDatabaseCount('test_cases', 0);
    $this->assertDatabaseCount('issues', 0);
});

test('test case draft generation is scoped to the active project and normalizes unavailable values', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Checkout',
        'key_prefix' => 'CHK',
    ]);
    $testPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Checkout hardening',
    ]);

    SeniorQualityAssuranceAgent::fake([
        [
            'draft' => [
                'cases' => [
                    [
                        'module_id' => 999999,
                        'title' => 'Guest checkout handles coupon removal',
                        'priority' => 'Top',
                        'automation_status' => 'Scripted',
                        'test_behavior' => 'Neutral',
                        'test_type' => 'Chaos',
                        'preconditions' => 'Cart contains a coupon code.',
                        'steps' => [
                            [
                                'action' => 'Open checkout as a guest user.',
                                'expected_result' => 'Checkout loads successfully.',
                            ],
                        ],
                    ],
                ],
            ],
            'warnings' => [],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'test_cases',
            'prompt' => 'Create focused checkout regression cases.',
            'module_id' => $module->id,
            'test_plan_id' => $testPlan->id,
        ])
        ->assertOk()
        ->assertJsonPath('draft.cases.0.module_id', $module->id)
        ->assertJsonPath('draft.cases.0.priority', TestCase::PRIORITY_MEDIUM)
        ->assertJsonPath('draft.cases.0.automation_status', TestCase::AUTOMATION_MANUAL)
        ->assertJsonPath('draft.cases.0.test_behavior', TestCase::BEHAVIOR_POSITIVE)
        ->assertJsonPath('draft.cases.0.test_type', TestCase::TYPE_FUNCTIONAL)
        ->assertJsonPath('source_context.selected_module.id', $module->id)
        ->assertJsonPath('source_context.selected_test_plan.id', $testPlan->id)
        ->assertJsonPath('source_context.requested_count', null)
        ->assertJsonPath('source_context.generated_count', 1)
        ->assertJsonPath('source_context.generation_limit', 20)
        ->assertJsonFragment([
            'One or more generated test cases were assigned to the selected module because the AI returned an unavailable module.',
        ])
        ->assertJsonFragment([
            'A generated test case priority was reset to Medium.',
        ])
        ->assertJsonFragment([
            'A generated automation status was reset to Manual.',
        ])
        ->assertJsonFragment([
            'A generated test behavior was reset to Positive.',
        ])
        ->assertJsonFragment([
            'A generated test type was reset to Functional.',
        ]);

    $this->assertDatabaseCount('test_plans', 1);
    $this->assertDatabaseCount('test_cases', 0);
    $this->assertDatabaseCount('issues', 0);

    SeniorQualityAssuranceAgent::assertPrompted(function ($prompt) use ($module, $testPlan): bool {
        return $prompt->agent->artifactType === 'test_cases'
            && $prompt->agent->requestedCount === null
            && $prompt->agent->maximumTestCaseCount === 20
            && $prompt->agent->context['selected_module']['id'] === $module->id
            && $prompt->agent->context['selected_test_plan']['id'] === $testPlan->id;
    });
});

test('test case generation count resolver recognizes explicit totals and ignores unrelated numbers', function (
    string $prompt,
    ?int $requested,
    ?int $effective,
    bool $wasLimited,
): void {
    $resolution = app(TestCaseGenerationCountResolver::class)->resolve($prompt);

    expect($resolution)
        ->requested->toBe($requested)
        ->effective->toBe($effective)
        ->was_limited->toBe($wasLimited);
})->with([
    'numeric count' => ['Generate 1 test case for login.', 1, 1, false],
    'word count' => ['Create five test cases for checkout.', 5, 5, false],
    'total suffix' => ['Write 10 test cases total.', 10, 10, false],
    'over limit' => ['Generate 25 test cases for payments.', 25, 20, true],
    'unrelated browser count' => ['Generate test cases for 3 supported browsers.', null, null, false],
]);

test('an explicit test case count is enforced when the ai returns extra drafts', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();

    $generatedCase = static fn (string $title): array => [
        'module_id' => $module->id,
        'title' => $title,
        'priority' => TestCase::PRIORITY_MEDIUM,
        'automation_status' => TestCase::AUTOMATION_MANUAL,
        'test_behavior' => TestCase::BEHAVIOR_POSITIVE,
        'test_type' => TestCase::TYPE_FUNCTIONAL,
        'preconditions' => null,
        'steps' => [],
    ];

    SeniorQualityAssuranceAgent::fake([
        [
            'draft' => [
                'cases' => [
                    $generatedCase('Expected draft'),
                    $generatedCase('Unexpected extra draft'),
                ],
            ],
            'warnings' => [],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'test_cases',
            'prompt' => 'Generate one test case for login.',
            'module_id' => $module->id,
            'test_plan_id' => $testPlan->id,
        ])
        ->assertOk()
        ->assertJsonCount(1, 'draft.cases')
        ->assertJsonPath('draft.cases.0.title', 'Expected draft')
        ->assertJsonPath('source_context.requested_count', 1)
        ->assertJsonPath('source_context.generated_count', 1)
        ->assertJsonPath('source_context.generation_limit', 20)
        ->assertJsonFragment([
            'The AI returned more than 1 test case, so extra drafts were removed.',
        ]);

    SeniorQualityAssuranceAgent::assertPrompted(function ($prompt): bool {
        return $prompt->agent->requestedCount === 1
            && $prompt->agent->maximumTestCaseCount === 20
            && str_contains((string) $prompt->agent->instructions(), 'Produce exactly 1 practical test case draft.');
    });
});

test('test case generation caps explicit and provider counts at the configured limit', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $generatedCases = collect(range(1, 21))
        ->map(fn (int $index): array => [
            'module_id' => $module->id,
            'title' => 'Generated case '.$index,
            'priority' => TestCase::PRIORITY_MEDIUM,
            'automation_status' => TestCase::AUTOMATION_MANUAL,
            'test_behavior' => TestCase::BEHAVIOR_POSITIVE,
            'test_type' => TestCase::TYPE_FUNCTIONAL,
            'preconditions' => null,
            'steps' => [],
        ])
        ->all();

    SeniorQualityAssuranceAgent::fake([
        [
            'draft' => ['cases' => $generatedCases],
            'warnings' => [],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'test_cases',
            'prompt' => 'Generate 25 test cases for checkout.',
            'module_id' => $module->id,
            'test_plan_id' => $testPlan->id,
        ])
        ->assertOk()
        ->assertJsonCount(20, 'draft.cases')
        ->assertJsonPath('source_context.requested_count', 25)
        ->assertJsonPath('source_context.generated_count', 20)
        ->assertJsonPath('source_context.generation_limit', 20)
        ->assertJsonFragment([
            'A request for 25 test cases exceeds the generation limit of 20, so the request was capped.',
        ])
        ->assertJsonFragment([
            'The AI returned more than 20 test cases, so extra drafts were removed.',
        ]);

    SeniorQualityAssuranceAgent::assertPrompted(fn ($prompt): bool => $prompt->agent->requestedCount === 20
        && $prompt->agent->maximumTestCaseCount === 20);
});

test('test case draft generation rejects foreign plan and module selections', function (): void {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $foreignPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $foreignModule = TestRepositoryModule::factory()->for($otherProject, 'project')->create();

    SeniorQualityAssuranceAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'test_cases',
            'prompt' => 'Generate repository cases.',
            'test_plan_id' => $foreignPlan->id,
            'module_id' => $foreignModule->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'test_plan_id',
            'module_id',
        ]);

    SeniorQualityAssuranceAgent::assertNeverPrompted();
});

test('issue draft generation rejects foreign artifacts outside the active project', function (): void {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $foreignPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    $activePlan = TestPlan::factory()->for($activeProject, 'project')->create();
    $foreignRun = TestRun::factory()->for($foreignPlan)->create();

    SeniorQualityAssuranceAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $activeProject->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'issues',
            'prompt' => 'Generate a defect draft.',
            'test_plan_id' => $activePlan->id,
            'test_run_id' => $foreignRun->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['test_run_id']);

    SeniorQualityAssuranceAgent::assertNeverPrompted();
});

test('issue draft generation relinks selected artifacts and does not create issues', function (): void {
    $user = User::factory()->create([
        'name' => 'Nadia QA',
    ]);
    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $testRun = TestRun::factory()->for($testPlan)->create();

    SeniorQualityAssuranceAgent::fake([
        [
            'draft' => [
                'issues' => [
                    [
                        'title' => 'Totals mismatch after address change',
                        'description' => 'Change the shipping address during checkout and continue to payment.',
                        'expected_result' => 'The tax total refreshes with the new address.',
                        'actual_result' => 'The previous tax total remains visible.',
                        'priority' => 'P1',
                        'severity' => 'Severe',
                        'status' => 'Solved',
                        'reporter' => '',
                        'assignee' => 'Rian',
                        'environment' => 'Lab',
                        'defect_type' => 'API',
                        'test_plan_id' => 88,
                        'test_run_id' => 99,
                    ],
                ],
            ],
            'warnings' => [],
        ],
    ])->preventStrayPrompts();

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'issues',
            'prompt' => 'Generate a checkout calculation bug draft.',
            'test_plan_id' => $testPlan->id,
            'test_run_id' => $testRun->id,
        ])
        ->assertOk()
        ->assertJsonPath('draft.issues.0.status', Issue::STATUS_OPEN)
        ->assertJsonPath('draft.issues.0.priority', Issue::PRIORITY_MEDIUM)
        ->assertJsonPath('draft.issues.0.severity', Issue::SEVERITY_MEDIUM)
        ->assertJsonPath('draft.issues.0.reporter', 'Nadia QA')
        ->assertJsonPath('draft.issues.0.environment', null)
        ->assertJsonPath('draft.issues.0.defect_type', null)
        ->assertJsonPath('draft.issues.0.test_plan_id', $testPlan->id)
        ->assertJsonPath('draft.issues.0.test_run_id', $testRun->id)
        ->assertJsonFragment([
            'A generated issue was re-linked to the selected test plan.',
        ])
        ->assertJsonFragment([
            'A generated issue was re-linked to the selected test run.',
        ])
        ->assertJsonFragment([
            'A generated issue priority was reset to Medium.',
        ])
        ->assertJsonFragment([
            'A generated issue severity was reset to Medium.',
        ])
        ->assertJsonFragment([
            'An invalid environment was removed from a generated issue.',
        ])
        ->assertJsonFragment([
            'An invalid defect type was removed from a generated issue.',
        ]);

    $this->assertDatabaseCount('issues', 0);

    SeniorQualityAssuranceAgent::assertPrompted(fn ($prompt): bool => $prompt->agent->artifactType === 'issues'
        && $prompt->agent->requestedCount === 1);
});

test('report summary generation requires an active project and returns structured narrative', function (): void {
    $user = User::factory()->create();

    SeniorQualityAssuranceAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'report_summary',
            'prompt' => 'Summarize the current QA posture.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['project']);

    SeniorQualityAssuranceAgent::fake([
        [
            'draft' => [
                'executive_summary' => 'Checkout quality is stable, with two risks needing follow-through.',
                'quality_risks' => ['Tax recalculation remains the main blocker.'],
                'coverage_notes' => ['Smoke coverage is complete for high-traffic paths.'],
                'issue_analysis' => ['Critical issues are concentrated in payment edge cases.'],
                'recommendations' => ['Keep daily regression on the checkout suite.'],
                'next_actions' => ['Retest the tax fix after deployment.'],
            ],
            'warnings' => ['Summary is based on the currently selected report filters.'],
        ],
    ])->preventStrayPrompts();

    $project = Project::factory()->for($user)->create();
    $testPlan = TestPlan::factory()->for($project, 'project')->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create();
    $testPlan->testCases()->attach($testCase->id);
    $testRun = TestRun::factory()->for($testPlan)->create([
        'created_at' => '2026-06-25 08:00:00',
        'updated_at' => '2026-06-25 08:00:00',
    ]);
    $testRun->testCases()->attach($testCase->id, [
        'execution_status' => TestRun::CASE_STATUS_FAILED,
    ]);
    Issue::factory()->for($project, 'project')->create([
        'test_plan_id' => $testPlan->id,
        'test_run_id' => $testRun->id,
        'severity' => Issue::SEVERITY_CRITICAL,
        'created_at' => '2026-06-25 09:00:00',
        'updated_at' => '2026-06-25 09:00:00',
    ]);

    $this->actingAs($user)
        ->withSession(['qa_workspace_project_id' => $project->project_id])
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'report_summary',
            'prompt' => 'Summarize the report for leadership.',
            'report_filters' => [
                'scope' => (string) $testPlan->id,
                'range' => '30d',
                'start_date' => '2026-05-27',
                'end_date' => '2026-06-27',
                'grouping' => 'weekly',
                'sections' => ['metrics', 'issue_distribution'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('draft.executive_summary', 'Checkout quality is stable, with two risks needing follow-through.')
        ->assertJsonPath('draft.quality_risks.0', 'Tax recalculation remains the main blocker.')
        ->assertJsonPath('source_context.project.id', $project->project_id)
        ->assertJsonPath('source_context.report_filters.plan_id', $testPlan->id)
        ->assertJsonPath('source_context.report_filters.grouping', 'weekly')
        ->assertJsonFragment([
            'Summary is based on the currently selected report filters.',
        ]);

    SeniorQualityAssuranceAgent::assertPrompted(function ($prompt) use ($testPlan): bool {
        return $prompt->agent->artifactType === 'report_summary'
            && $prompt->agent->context['report_filters']['plan_id'] === $testPlan->id;
    });
});
