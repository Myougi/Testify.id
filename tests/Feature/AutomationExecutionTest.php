<?php

use App\Jobs\DispatchAutomationRun;
use App\Models\AutomationExecution;
use App\Models\Project;
use App\Models\ProjectEnvironment;
use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationResultService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('automation.enabled', true);
    config()->set('automation.github', [
        'owner' => 'testify',
        'repository' => 'testify',
        'workflow' => 'automated-test-run.yml',
        'ref' => 'main',
        'token' => 'github-token',
        'api_url' => 'https://api.github.com',
    ]);
    config()->set('automation.runner.shared_secret', 'runner-secret');
    Queue::fake();
});

test('start remains unchanged for manual only runs', function (): void {
    [$user, $run] = automationRunFixture(false);

    $this->actingAs($user)
        ->patchJson("/api/test-runs/{$run->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', TestRun::STATUS_IN_PROGRESS);

    expect($run->automationRuns()->count())->toBe(0);
});

test('start is atomic and reports every automated case without a ready definition', function (): void {
    [$user, $run, $case] = automationRunFixture(true);

    $this->actingAs($user)
        ->patchJson("/api/test-runs/{$run->id}/start")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('automation')
        ->assertJsonPath('errors.automation.0', "Automated cases require a current Ready definition: {$case->case_key}.");

    expect($run->fresh()->status)->toBe(TestRun::STATUS_NEW)
        ->and($run->automationRuns()->count())->toBe(0);
});

test('ready automated cases queue snapshots and mixed runs remain in progress', function (): void {
    [$user, $run, $automatedCase, $manualCase] = automationRunFixture(true, true);
    makeAutomationReady($automatedCase, $user);

    $this->actingAs($user)
        ->patchJson("/api/test-runs/{$run->id}/start")
        ->assertOk();

    Queue::assertPushed(DispatchAutomationRun::class);
    $automationRun = $run->automationRuns()->with('executions')->firstOrFail();
    $execution = $automationRun->executions->firstOrFail();
    expect($execution->definition_snapshot)->toBeArray();

    $results = app(AutomationResultService::class);
    $results->start($automationRun, '123', 'https://github.com/example/actions/runs/123');
    expect($run->testCases()->whereKey($automatedCase->id)->firstOrFail()->pivot->execution_status)
        ->toBe(TestRun::CASE_STATUS_IN_TESTING);

    $results->completeExecution($execution->fresh(), [
        'status' => AutomationExecution::STATUS_PASSED,
        'duration_ms' => 250,
    ]);

    expect($run->fresh()->status)->toBe(TestRun::STATUS_IN_PROGRESS)
        ->and($run->testCases()->whereKey($manualCase->id)->firstOrFail()->pivot->execution_status)
        ->toBe(TestRun::CASE_STATUS_NOT_RUN);
});

/** @return array{User, TestRun, TestCase, TestCase|null} */
function automationRunFixture(bool $automated, bool $withManual = false): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $plan = TestPlan::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
        'automation_status' => $automated ? TestCase::AUTOMATION_AUTOMATED : TestCase::AUTOMATION_MANUAL,
    ]);
    TestCaseStep::factory()->for($case)->create(['position' => 1]);
    $run = TestRun::factory()->for($plan)->create([
        'environment' => TestRun::ENVIRONMENT_SIT,
        'status' => TestRun::STATUS_NEW,
    ]);
    $run->testCases()->attach($case);
    ProjectEnvironment::factory()->for($project, 'project')->create([
        'environment' => TestRun::ENVIRONMENT_SIT,
        'base_url' => 'https://sit.example.test',
    ]);
    $manualCase = null;

    if ($withManual) {
        $manualCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
            'automation_status' => TestCase::AUTOMATION_MANUAL,
        ]);
        $run->testCases()->attach($manualCase);
    }

    return [$user, $run, $case, $manualCase];
}

function makeAutomationReady(TestCase $case, User $user): void
{
    $service = app(AutomationDefinitionService::class);
    $automation = $service->save($case, [
        'version' => 1,
        'steps' => [['action' => 'navigate', 'path' => '/']],
    ]);
    $service->markValidationResult($automation, TestRun::ENVIRONMENT_SIT, true, 'Passed.');
    $service->approve($automation->fresh(), $user);
}
