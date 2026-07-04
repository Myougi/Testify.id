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
use App\Services\Automation\AutomationResultService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

test('runner rejects expired signatures and replayed request identifiers', function (): void {
    [$run] = runnerCallbackFixture();
    $path = "/api/runner/v1/automation-runs/{$run->id}/start";
    $body = '{}';
    $requestId = (string) Str::uuid();

    signedRunnerPost($this, $path, $body, 'expired', $requestId, now()->subMinutes(10)->timestamp)
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
        'failure_message' => 'Expected heading was not visible.',
        'failed_step' => 2,
    ], JSON_THROW_ON_ERROR);
    signedRunnerPost($this, $resultPath, $body, 'result-failed')->assertOk();

    expect($testRun->testCases()->whereKey($case->id)->firstOrFail()->pivot->execution_status)
        ->toBe(TestRun::CASE_STATUS_FAILED)
        ->and($run->fresh()->status)->toBe(AutomationRun::STATUS_FAILED);
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

function signedRunnerPost(
    object $testCase,
    string $path,
    string $body,
    ?string $idempotencyKey,
    ?string $requestId = null,
    ?int $timestamp = null,
): TestResponse {
    $timestamp ??= now()->timestamp;
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
