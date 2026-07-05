<?php

use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationResultService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase as FeatureTestCase;

beforeEach(function (): void {
    config()->set('automation.runner.shared_secret', 'runner-secret');
    Cache::flush();
});

test('runner callbacks require valid signatures and idempotency keys', function (): void {
    [$run] = runnerCallbackFixture();
    $path = "/api/runner/v1/automation-runs/{$run->id}/start";
    $body = json_encode(['provider_run_id' => '100'], JSON_THROW_ON_ERROR);

    signedRunnerPost($this, $path, $body, null)->assertBadRequest();
    signedRunnerPost($this, $path, $body, 'start-100')->assertOk();
    signedRunnerPost($this, $path, $body, 'start-100')->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($run->fresh()->status)->toBe(AutomationRun::STATUS_RUNNING);
});

test('runner show reuses the signed request identifier for audit logging', function (): void {
    [$run] = runnerCallbackFixture();
    $path = "/api/runner/v1/automation-runs/{$run->id}";
    $requestId = (string) Str::uuid();

    signedRunnerGet($this, $path, $requestId)
        ->assertOk()
        ->assertHeader('X-Request-ID', $requestId)
        ->assertJsonPath('data.id', $run->id);

    $this->assertDatabaseHas('api_audit_logs', [
        'action' => 'automation_run.claimed',
        'resource_id' => $run->id,
        'request_id' => $requestId,
    ]);
});

test('runner rejects expired signatures and replayed request identifiers', function (): void {
    [$run] = runnerCallbackFixture();
    $path = "/api/runner/v1/automation-runs/{$run->id}/start";
    $body = '{}';
    $requestId = (string) Str::uuid();

    signedRunnerPost($this, $path, $body, 'expired', $requestId, now()->subMinutes(10)->getTimestamp())
        ->assertUnauthorized();

    signedRunnerPost($this, $path, $body, 'replay', $requestId)->assertOk();
    signedRunnerPost($this, $path, $body, 'replay', $requestId)->assertUnauthorized();
});

test('runner result callbacks map automation status to the test run case result', function (): void {
    [$run, $execution, $testRun, $case] = runnerCallbackFixture();
    $startPath = "/api/runner/v1/automation-runs/{$run->id}/start";
    signedRunnerPost($this, $startPath, '{}', 'start-result')->assertOk();

    $resultPath = "/api/runner/v1/automation-runs/{$run->id}/executions/{$execution->id}/result";
    $body = json_encode([
        'status' => AutomationExecution::STATUS_FAILED,
        'duration_ms' => 1200,
        'failure_type' => 'assertion',
        'failure_code' => 'assertion_failed',
        'failure_message' => 'Expected heading was not visible.',
        'failed_step' => 2,
    ], JSON_THROW_ON_ERROR);
    signedRunnerPost($this, $resultPath, $body, 'result-failed')->assertOk();

    $executionStatus = DB::table('test_case_test_run')
        ->where('test_run_id', $testRun->id)
        ->where('test_case_id', $case->id)
        ->value('execution_status');

    expect($executionStatus)
        ->toBe(TestRun::CASE_STATUS_FAILED)
        ->and($execution->fresh()->failure_code)->toBe('assertion_failed')
        ->and($run->fresh()->status)->toBe(AutomationRun::STATUS_FAILED);
});

test('signed generation callbacks update progress and save a browser recorded draft', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $sourceHash = app(AutomationDefinitionService::class)->sourceHash($case);
    $run = AutomationRun::factory()->for($user, 'requester')->create([
        'kind' => AutomationRun::KIND_GENERATION,
        'test_run_id' => null,
        'generation_source' => AutomationRun::SOURCE_AGENTIC,
        'source_hash' => $sourceHash,
        'phase' => AutomationRun::PHASE_STARTING_BROWSER,
    ]);
    $execution = AutomationExecution::factory()->for($run, 'automationRun')->for($case)->create([
        'definition_snapshot' => null,
    ]);
    signedRunnerPost($this, "/api/runner/v1/automation-runs/{$run->id}/start", '{}', 'generation-start')->assertOk();
    $progressBody = json_encode([
        'phase' => AutomationRun::PHASE_EXPLORING,
        'message' => 'Exploring browser turn 1 of 30.',
    ], JSON_THROW_ON_ERROR);
    signedRunnerPost(
        $this,
        "/api/runner/v1/automation-runs/{$run->id}/progress",
        $progressBody,
        'generation-progress',
    )->assertOk();
    $resultBody = json_encode([
        'success' => true,
        'definition' => [
            'version' => 1,
            'steps' => [['action' => 'navigate', 'path' => '/login']],
        ],
        'warnings' => [],
        'duration_ms' => 1500,
    ], JSON_THROW_ON_ERROR);
    signedRunnerPost(
        $this,
        "/api/runner/v1/automation-runs/{$run->id}/executions/{$execution->id}/generation-result",
        $resultBody,
        'generation-result',
    )->assertOk();

    expect($run->fresh()->phase)->toBe(AutomationRun::PHASE_DRAFT_RECORDED)
        ->and($case->fresh()->automation?->generation_source)->toBe(AutomationRun::SOURCE_AGENTIC)
        ->and($case->fresh()->automation?->last_validated_at)->toBeNull();
});

test('signed generation callbacks store a single readable failure summary for invalid returned dsl', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $sourceHash = app(AutomationDefinitionService::class)->sourceHash($case);
    $run = AutomationRun::factory()->for($user, 'requester')->create([
        'kind' => AutomationRun::KIND_GENERATION,
        'test_run_id' => null,
        'generation_source' => AutomationRun::SOURCE_AGENTIC,
        'source_hash' => $sourceHash,
        'phase' => AutomationRun::PHASE_STARTING_BROWSER,
    ]);
    $execution = AutomationExecution::factory()->for($run, 'automationRun')->for($case)->create([
        'definition_snapshot' => null,
    ]);

    signedRunnerPost($this, "/api/runner/v1/automation-runs/{$run->id}/start", '{}', 'generation-start-invalid')->assertOk();

    $resultBody = json_encode([
        'success' => true,
        'definition' => [
            'version' => 1,
            'steps' => [['action' => 'click']],
        ],
        'warnings' => [],
        'duration_ms' => 1500,
    ], JSON_THROW_ON_ERROR);

    signedRunnerPost(
        $this,
        "/api/runner/v1/automation-runs/{$run->id}/executions/{$execution->id}/generation-result",
        $resultBody,
        'generation-result-invalid',
    )->assertOk();

    expect($run->fresh()->status)->toBe(AutomationRun::STATUS_FAILED)
        ->and($run->fresh()->progress_message)->toBe('Step 1 click is missing locator_by.')
        ->and($run->fresh()->failure_message)->toBe('Step 1 click is missing locator_by.')
        ->and($execution->fresh()->status)->toBe(AutomationExecution::STATUS_FAILED)
        ->and($execution->fresh()->failure_message)->toBe('Step 1 click is missing locator_by.');
});

test('artifacts become visible only after size and checksum verification', function (): void {
    [, $execution] = runnerCallbackFixture();
    Storage::fake('automation-artifacts');
    config()->set('filesystems.automation_artifacts_disk', 'automation-artifacts');
    $contents = 'sanitized runner log';
    $path = "automation/test/{$execution->id}/console.log";
    Storage::disk('automation-artifacts')->put($path, $contents);
    $artifact = $execution->artifacts()->create([
        'type' => AutomationArtifact::TYPE_LOG,
        'disk' => 'automation-artifacts',
        'path' => $path,
        'original_name' => 'console.log',
        'mime_type' => 'text/plain',
        'size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
    ]);

    expect($artifact->uploaded_at)->toBeNull();

    $finalized = app(AutomationResultService::class)->finalizeArtifact($execution, $artifact);

    expect($finalized->uploaded_at)->not->toBeNull();
});

/** @return array{AutomationRun, AutomationExecution, TestRun, TestCase} */
function runnerCallbackFixture(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $plan = TestPlan::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
        'automation_status' => TestCase::AUTOMATION_AUTOMATED,
    ]);
    $testRun = TestRun::factory()->for($plan)->create([
        'status' => TestRun::STATUS_IN_PROGRESS,
        'environment' => TestRun::ENVIRONMENT_SIT,
    ]);
    $testRun->testCases()->attach($case);
    $run = AutomationRun::factory()->for($testRun)->for($user, 'requester')->create();
    $execution = AutomationExecution::factory()->for($run, 'automationRun')->for($case)->create();

    return [$run, $execution, $testRun, $case];
}

/**
 * @return TestResponse<Response>
 */
function signedRunnerPost(
    FeatureTestCase $testCase,
    string $path,
    string $body,
    ?string $idempotencyKey,
    ?string $requestId = null,
    ?int $timestamp = null,
): TestResponse {
    $timestamp ??= now()->getTimestamp();
    $requestId ??= (string) Str::uuid();
    $canonical = implode("\n", [
        (string) $timestamp,
        'POST',
        $path,
        hash('sha256', $body),
    ]);
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TESTIFY_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_TESTIFY_REQUEST_ID' => $requestId,
        'HTTP_X_TESTIFY_SIGNATURE' => hash_hmac('sha256', $canonical, 'runner-secret'),
    ];

    if ($idempotencyKey !== null) {
        $headers['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
    }

    return $testCase->call('POST', $path, [], [], [], $headers, $body);
}

/**
 * @return TestResponse<Response>
 */
function signedRunnerGet(
    FeatureTestCase $testCase,
    string $path,
    ?string $requestId = null,
    ?int $timestamp = null,
): TestResponse {
    $timestamp ??= now()->getTimestamp();
    $requestId ??= (string) Str::uuid();
    $canonical = implode("\n", [
        (string) $timestamp,
        'GET',
        $path,
        hash('sha256', ''),
    ]);

    return $testCase->call('GET', $path, [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TESTIFY_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_TESTIFY_REQUEST_ID' => $requestId,
        'HTTP_X_TESTIFY_SIGNATURE' => hash_hmac('sha256', $canonical, 'runner-secret'),
    ]);
}
