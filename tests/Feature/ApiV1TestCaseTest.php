<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Support\Api\V1\JsonApi;
use Laravel\Sanctum\Sanctum;

const TEST_CASE_JSON_API = 'application/vnd.api+json';

function compoundTestCasePayload(Project $project, TestRepositoryModule $module, string $title = 'Successful login'): array
{
    return [
        'data' => [
            'type' => 'test-cases',
            'attributes' => [
                'title' => $title,
                'priority' => 'High',
                'automation_status' => 'Manual',
                'test_behavior' => 'Positive',
                'test_type' => 'Functional',
                'preconditions' => 'Registered user.',
            ],
            'relationships' => [
                'project' => ['data' => ['type' => 'projects', 'id' => (string) $project->project_id]],
                'module' => ['data' => ['type' => 'repository-modules', 'id' => (string) $module->id]],
                'steps' => ['data' => [
                    ['type' => 'test-case-steps', 'lid' => 'step-1'],
                    ['type' => 'test-case-steps', 'lid' => 'step-2'],
                ]],
            ],
        ],
        'included' => [
            ['type' => 'test-case-steps', 'lid' => 'step-1', 'attributes' => [
                'step_number' => 1,
                'action' => 'Open login page.',
                'expected_result' => 'Login page is visible.',
            ]],
            ['type' => 'test-case-steps', 'lid' => 'step-2', 'attributes' => [
                'step_number' => 2,
                'action' => 'Submit valid credentials.',
                'expected_result' => 'Dashboard is visible.',
            ]],
        ],
    ];
}

test('test case and included steps are created atomically and idempotently', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create(['key_prefix' => 'LG']);
    Sanctum::actingAs($user, ['test-cases:write']);
    $payload = compoundTestCasePayload($project, $module);
    $headers = [
        'Accept' => TEST_CASE_JSON_API,
        'Content-Type' => TEST_CASE_JSON_API,
        'Idempotency-Key' => 'create-login-case',
    ];

    $response = $this->json('POST', '/api/v1/test-cases', $payload, $headers);

    $response->assertCreated()
        ->assertHeader('ETag')
        ->assertJsonPath('data.attributes.case_key', 'TC-LG-01')
        ->assertJsonCount(2, 'included');
    $this->assertDatabaseCount('test_cases', 1);
    $this->assertDatabaseCount('test_case_steps', 2);

    $this->json('POST', '/api/v1/test-cases', $payload, $headers)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');
    $this->assertDatabaseCount('test_cases', 1);

    $changed = compoundTestCasePayload($project, $module, 'Different title');
    $this->json('POST', '/api/v1/test-cases', $changed, $headers)
        ->assertConflict()
        ->assertJsonPath('errors.0.code', 'idempotency_key_conflict');
});

test('patch replaces steps only when supplied and enforces etags', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $testCase->steps()->create(['position' => 1, 'action' => 'Old', 'expected_result' => 'Old result']);
    Sanctum::actingAs($user, ['test-cases:write']);
    $etag = JsonApi::etag($testCase);

    $this->json('PATCH', "/api/v1/test-cases/{$testCase->id}", [
        'data' => ['type' => 'test-cases', 'attributes' => ['title' => 'Renamed']],
    ], ['Accept' => TEST_CASE_JSON_API, 'Content-Type' => TEST_CASE_JSON_API])
        ->assertStatus(428);

    $this->json('PATCH', "/api/v1/test-cases/{$testCase->id}", [
        'data' => [
            'type' => 'test-cases',
            'attributes' => ['title' => 'Renamed'],
            'relationships' => ['steps' => ['data' => [
                ['type' => 'test-case-steps', 'lid' => 'replacement'],
            ]]],
        ],
        'included' => [[
            'type' => 'test-case-steps',
            'lid' => 'replacement',
            'attributes' => [
                'step_number' => 1,
                'action' => 'New action',
                'expected_result' => 'New result',
            ],
        ]],
    ], ['Accept' => TEST_CASE_JSON_API, 'Content-Type' => TEST_CASE_JSON_API, 'If-Match' => $etag])
        ->assertOk()
        ->assertJsonPath('included.0.attributes.action', 'New action');

    expect($testCase->fresh()->steps()->count())->toBe(1);
    expect($testCase->fresh()->steps()->first()->action)->toBe('New action');
});

test('deleting a test case with related plans or runs returns a relationship conflict', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $plan = TestPlan::factory()->for($project, 'project')->create();
    $run = TestRun::factory()->for($plan)->create();
    $testCase = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $plan->testCases()->attach($testCase->id);
    $run->testCases()->attach($testCase->id, ['execution_status' => TestRun::CASE_STATUS_NOT_RUN]);

    Sanctum::actingAs($user, ['test-cases:write']);
    $etag = JsonApi::etag($testCase);

    $this->json('DELETE', "/api/v1/test-cases/{$testCase->id}", [], [
        'Accept' => TEST_CASE_JSON_API,
        'Content-Type' => TEST_CASE_JSON_API,
        'If-Match' => $etag,
    ])
        ->assertConflict()
        ->assertJsonPath('errors.0.code', 'resource_has_relationships');
});
