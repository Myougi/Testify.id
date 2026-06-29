<?php

use App\Ai\Agents\SeniorQualityAssuranceAgent;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\ReportPdfService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-22 10:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('guests are redirected from the reports page', function (): void {
    $this->get(route('reports.index'))
        ->assertRedirect(route('login'));

    $this->postJson(route('reports.export'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view computed project reports', function (): void {
    $data = createReportDataset();

    $response = $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/Index')
        ->where('initialPayload.options.hasActiveProject', true)
        ->where('initialPayload.options.project.name', 'Helios QA')
        ->where('initialPayload.metrics.totalTestCases', 3)
        ->where('initialPayload.metrics.totalTestRuns', 2)
        ->where('initialPayload.metrics.totalIssues', 2)
        ->where('initialPayload.metrics.executionCoverage', 66.7)
        ->where('initialPayload.distribution.0.label', Issue::SEVERITY_LOW)
        ->where('initialPayload.distribution.0.value', 0)
        ->where('initialPayload.distribution.2.label', Issue::SEVERITY_HIGH)
        ->where('initialPayload.distribution.2.value', 1)
        ->where('initialPayload.distribution.3.label', Issue::SEVERITY_CRITICAL)
        ->where('initialPayload.distribution.3.value', 1)
        ->where('initialPayload.results.statuses.0.label', TestRun::CASE_STATUS_PASSED)
        ->where('initialPayload.results.statuses.0.value', 1)
        ->where('initialPayload.results.statuses.1.label', TestRun::CASE_STATUS_FAILED)
        ->where('initialPayload.results.statuses.1.value', 1)
        ->where('initialPayload.results.statuses.2.label', TestRun::CASE_STATUS_IN_TESTING)
        ->where('initialPayload.results.statuses.2.value', 0)
        ->where('initialPayload.results.statuses.5.label', TestRun::CASE_STATUS_NOT_RUN)
        ->where('initialPayload.results.statuses.5.value', 1)
        ->where('initialPayload.workload.0.name', 'Sarah Chen')
        ->where('initialPayload.workload.0.activeRuns', 1)
        ->where('initialPayload.workload.0.assignedCases', 2)
        ->where('initialPayload.recentExecutions.0.name', 'Beta smoke run')
        ->where('initialPayload.recentExecutions.1.name', 'Alpha regression run'));
});

test('reports only include the active users active project data', function (): void {
    $data = createReportDataset();

    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $otherPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    TestRun::factory()->for($otherPlan)->create([
        'created_at' => '2026-06-22 08:00:00',
        'updated_at' => '2026-06-22 08:00:00',
    ]);
    Issue::factory()->for($otherProject, 'project')->create([
        'test_plan_id' => $otherPlan->id,
        'created_at' => '2026-06-22 08:00:00',
        'updated_at' => '2026-06-22 08:00:00',
    ]);

    $response = $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('initialPayload.metrics.totalTestRuns', 2)
        ->where('initialPayload.metrics.totalIssues', 2));
});

test('selected plan scope excludes other plans in the active project', function (): void {
    $data = createReportDataset();

    $response = $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->get(route('reports.index', ['scope' => $data['alphaPlan']->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('initialPayload.filters.scope', (string) $data['alphaPlan']->id)
        ->where('initialPayload.metrics.totalTestCases', 2)
        ->where('initialPayload.metrics.totalTestRuns', 1)
        ->where('initialPayload.metrics.totalIssues', 1)
        ->where('initialPayload.metrics.executionCoverage', 100)
        ->has('initialPayload.recentExecutions', 1)
        ->where('initialPayload.recentExecutions.0.plan.name', 'Alpha Release'));
});

test('date range filters affect report metrics and recent executions', function (): void {
    $data = createReportDataset();

    $response = $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->get(route('reports.index', [
            'range' => 'custom',
            'start_date' => '2026-06-21',
            'end_date' => '2026-06-22',
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('initialPayload.filters.start_date', '2026-06-21')
        ->where('initialPayload.filters.end_date', '2026-06-22')
        ->where('initialPayload.metrics.totalTestRuns', 1)
        ->where('initialPayload.metrics.totalIssues', 1)
        ->where('initialPayload.metrics.executionCoverage', 0)
        ->has('initialPayload.recentExecutions', 1)
        ->where('initialPayload.recentExecutions.0.name', 'Beta smoke run'));
});

test('reports reject plans outside the active project', function (): void {
    $data = createReportDataset();
    $otherProject = Project::factory()->for($data['user'])->create();
    $otherPlan = TestPlan::factory()->for($otherProject, 'project')->create();

    $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->get(route('reports.index', ['scope' => $otherPlan->id]))
        ->assertNotFound();
});

test('users can export a scoped ai generated pdf report', function (): void {
    $data = createReportDataset();
    SeniorQualityAssuranceAgent::fake([
        reportSummaryAgentResponse(),
    ])->preventStrayPrompts();

    $response = $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->postJson(route('reports.export'), [
            'scope' => (string) $data['alphaPlan']->id,
            'range' => '30d',
            'grouping' => 'weekly',
            'sections' => 'metrics,issue_trend,recent_executions',
        ]);

    $response
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('testify-helios-qa-alpha-release-2026-06-22.pdf');

    expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();

    SeniorQualityAssuranceAgent::assertPrompted(function ($prompt) use ($data): bool {
        $snapshot = $prompt->agent->context['report_snapshot'];

        return $prompt->agent->artifactType === 'report_summary'
            && $prompt->agent->context['project']['id'] === $data['project']->project_id
            && $prompt->agent->context['report_filters']['plan_id'] === $data['alphaPlan']->id
            && $snapshot['metrics']['totalTestCases'] === 2
            && count($snapshot['issue_trend']) === 6
            && $snapshot['recent_executions'][0]['name'] === 'Alpha regression run'
            && ! isset($snapshot['issue_distribution']);
    });
});

test('pdf export requires an active project', function (): void {
    $user = User::factory()->create();
    SeniorQualityAssuranceAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('reports.export'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Select an active project before exporting a report.');

    SeniorQualityAssuranceAgent::assertNeverPrompted();
});

test('pdf export rejects plans outside the active project', function (): void {
    $data = createReportDataset();
    $otherProject = Project::factory()->for($data['user'])->create();
    $otherPlan = TestPlan::factory()->for($otherProject, 'project')->create();
    SeniorQualityAssuranceAgent::fake()->preventStrayPrompts();

    $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->postJson(route('reports.export'), [
            'scope' => (string) $otherPlan->id,
        ])
        ->assertNotFound();

    SeniorQualityAssuranceAgent::assertNeverPrompted();
});

test('pdf export returns a safe error when the ai provider fails', function (): void {
    $data = createReportDataset();
    SeniorQualityAssuranceAgent::fake(function (): never {
        throw new \RuntimeException('Provider unavailable.');
    });

    $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->postJson(route('reports.export'))
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI report could not be generated. Please try again.');
});

test('pdf export returns a safe error when rendering fails', function (): void {
    $data = createReportDataset();
    SeniorQualityAssuranceAgent::fake([
        reportSummaryAgentResponse(),
    ])->preventStrayPrompts();
    $this->mock(ReportPdfService::class, function ($mock): void {
        $mock->shouldReceive('download')
            ->once()
            ->andThrow(new \RuntimeException('Renderer unavailable.'));
    });

    $this
        ->actingAs($data['user'])
        ->withSession(['qa_workspace_project_id' => $data['project']->project_id])
        ->postJson(route('reports.export'))
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI report could not be generated. Please try again.');
});

test('reports render an empty state when no active project is selected', function (): void {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reports/Index')
        ->where('initialPayload.options.hasActiveProject', false)
        ->where('initialPayload.metrics.totalTestCases', 0)
        ->where('initialPayload.recentExecutions', []));
});

/**
 * @return array{user: User, project: Project, alphaPlan: TestPlan, betaPlan: TestPlan}
 */
function createReportDataset(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'project_name' => 'Helios QA',
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create([
        'name' => 'Checkout',
    ]);
    $alphaPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Alpha Release',
    ]);
    $betaPlan = TestPlan::factory()->for($project, 'project')->create([
        'name' => 'Beta Sprint',
    ]);
    $alphaCases = TestCase::factory()
        ->count(2)
        ->for($project, 'project')
        ->for($module, 'module')
        ->sequence(
            ['case_key' => 'ALPHA-001', 'title' => 'Card checkout succeeds'],
            ['case_key' => 'ALPHA-002', 'title' => 'Wallet checkout fails gracefully'],
        )
        ->create();
    $betaCase = TestCase::factory()
        ->for($project, 'project')
        ->for($module, 'module')
        ->create([
            'case_key' => 'BETA-001',
            'title' => 'Guest checkout smoke test',
        ]);

    $alphaPlan->testCases()->attach($alphaCases->pluck('id'));
    $betaPlan->testCases()->attach($betaCase->id);

    $alphaRun = TestRun::factory()->for($alphaPlan)->create([
        'name' => 'Alpha regression run',
        'tester_name' => 'Sarah Chen',
        'status' => TestRun::STATUS_IN_PROGRESS,
        'created_at' => '2026-06-20 09:00:00',
        'updated_at' => '2026-06-20 09:00:00',
    ]);
    $alphaRun->testCases()->attach($alphaCases[0]->id, [
        'execution_status' => TestRun::CASE_STATUS_PASSED,
    ]);
    $alphaRun->testCases()->attach($alphaCases[1]->id, [
        'execution_status' => TestRun::CASE_STATUS_FAILED,
    ]);

    $betaRun = TestRun::factory()->for($betaPlan)->create([
        'name' => 'Beta smoke run',
        'tester_name' => 'Marcus Wright',
        'status' => TestRun::STATUS_COMPLETED,
        'created_at' => '2026-06-21 09:00:00',
        'updated_at' => '2026-06-21 09:00:00',
    ]);
    $betaRun->testCases()->attach($betaCase->id, [
        'execution_status' => TestRun::CASE_STATUS_NOT_RUN,
    ]);

    TestRun::factory()->for($alphaPlan)->create([
        'name' => 'Old alpha run',
        'tester_name' => 'Sarah Chen',
        'created_at' => '2026-05-01 09:00:00',
        'updated_at' => '2026-05-01 09:00:00',
    ]);

    Issue::factory()->for($project, 'project')->create([
        'test_plan_id' => $alphaPlan->id,
        'test_run_id' => $alphaRun->id,
        'severity' => Issue::SEVERITY_CRITICAL,
        'created_at' => '2026-06-20 11:00:00',
        'updated_at' => '2026-06-20 11:00:00',
    ]);
    Issue::factory()->for($project, 'project')->create([
        'test_plan_id' => $betaPlan->id,
        'test_run_id' => $betaRun->id,
        'severity' => Issue::SEVERITY_HIGH,
        'created_at' => '2026-06-21 11:00:00',
        'updated_at' => '2026-06-21 11:00:00',
    ]);
    Issue::factory()->for($project, 'project')->create([
        'test_plan_id' => $alphaPlan->id,
        'test_run_id' => $alphaRun->id,
        'severity' => Issue::SEVERITY_LOW,
        'created_at' => '2026-05-01 11:00:00',
        'updated_at' => '2026-05-01 11:00:00',
    ]);

    return [
        'user' => $user,
        'project' => $project,
        'alphaPlan' => $alphaPlan,
        'betaPlan' => $betaPlan,
    ];
}

/**
 * @return array{draft: array<string, mixed>, warnings: array<int, string>}
 */
function reportSummaryAgentResponse(): array
{
    return [
        'draft' => [
            'executive_summary' => 'The release is at risk until the failed checkout path is resolved.',
            'quality_risks' => ['A failed checkout case blocks release confidence.'],
            'coverage_notes' => ['The selected plan has complete execution coverage.'],
            'issue_analysis' => ['The critical issue is concentrated in checkout.'],
            'recommendations' => ['Resolve and retest the checkout failure first.'],
            'next_actions' => ['Assign the fix, rerun regression, and review release readiness.'],
        ],
        'warnings' => ['Analysis is limited to the selected report sections.'],
    ];
}
