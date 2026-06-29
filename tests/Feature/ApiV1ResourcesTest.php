<?php

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Support\Api\V1\JsonApi;
use Laravel\Sanctum\Sanctum;

const RESOURCE_JSON_API = 'application/vnd.api+json';

test('owned resource collections are available and foreign resources are hidden', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $foreignProject = Project::factory()->for($other)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $plan = TestPlan::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $run = TestRun::factory()->for($plan, 'testPlan')->create();
    Issue::factory()->for($project, 'project')->create();
    Sanctum::actingAs($user, [
        'projects:read', 'repository-modules:read', 'test-plans:read',
        'test-cases:read', 'test-runs:read', 'issues:read',
    ]);
    $headers = ['Accept' => RESOURCE_JSON_API];

    foreach (['projects', 'repository-modules', 'test-plans', 'test-cases', 'test-runs', 'issues'] as $endpoint) {
        $this->getJson("/api/v1/{$endpoint}", $headers)
            ->assertOk()
            ->assertHeader('ETag')
            ->assertJsonCount(1, 'data');
    }

    $this->getJson("/api/v1/projects/{$foreignProject->project_id}", $headers)
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'not_found');
});

test('project writes require idempotency and etags and deletes are soft', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['projects:read', 'projects:write']);
    $headers = ['Accept' => RESOURCE_JSON_API, 'Content-Type' => RESOURCE_JSON_API];
    $payload = ['data' => ['type' => 'projects', 'attributes' => [
        'name' => 'API Project',
        'description' => 'Created through API',
        'status' => 'Active',
    ]]];

    $this->json('POST', '/api/v1/projects', $payload, $headers)
        ->assertBadRequest()
        ->assertJsonPath('errors.0.code', 'idempotency_key_required');

    $created = $this->json('POST', '/api/v1/projects', $payload, [
        ...$headers,
        'Idempotency-Key' => 'project-create',
    ])->assertCreated();
    $id = $created->json('data.id');
    $etag = $created->headers->get('ETag');

    $this->json('PATCH', "/api/v1/projects/{$id}", [
        'data' => ['type' => 'projects', 'attributes' => ['name' => 'Updated Project']],
    ], [...$headers, 'If-Match' => '"stale"'])
        ->assertStatus(412);

    $updated = $this->json('PATCH', "/api/v1/projects/{$id}", [
        'data' => ['type' => 'projects', 'attributes' => ['name' => 'Updated Project']],
    ], [...$headers, 'If-Match' => $etag])
        ->assertOk();

    $this->deleteJson("/api/v1/projects/{$id}", [], [
        'Accept' => RESOURCE_JSON_API,
        'If-Match' => $updated->headers->get('ETag'),
    ])->assertNoContent();

    $this->assertSoftDeleted('projects', ['project_id' => $id]);
});

test('cursor pagination and sparse fieldsets follow the json api contract', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->for($user)->create();
    Sanctum::actingAs($user, ['projects:read']);

    $response = $this->getJson('/api/v1/projects?page[size]=2&fields[projects]=name', [
        'Accept' => RESOURCE_JSON_API,
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.page.size', 2)
        ->assertJsonStructure(['links' => ['first', 'prev', 'next']]);

    expect(array_keys($response->json('data.0.attributes')))->toBe(['name']);
});

test('module plan run lifecycle and issue writes use the versioned contract', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Sanctum::actingAs($user, [
        'repository-modules:write', 'test-plans:write', 'test-runs:write', 'issues:write',
    ]);
    $headers = ['Accept' => RESOURCE_JSON_API, 'Content-Type' => RESOURCE_JSON_API];

    $moduleResponse = $this->json('POST', '/api/v1/repository-modules', [
        'data' => [
            'type' => 'repository-modules',
            'attributes' => ['name' => 'Login', 'key_prefix' => 'LG'],
            'relationships' => [
                'project' => ['data' => ['type' => 'projects', 'id' => (string) $project->project_id]],
            ],
        ],
    ], [...$headers, 'Idempotency-Key' => 'module-create'])->assertCreated();
    $module = TestRepositoryModule::query()->findOrFail($moduleResponse->json('data.id'));

    $planResponse = $this->json('POST', '/api/v1/test-plans', [
        'data' => [
            'type' => 'test-plans',
            'attributes' => [
                'name' => 'Release validation',
                'status' => 'Draft',
                'priority' => 'High',
            ],
            'relationships' => [
                'project' => ['data' => ['type' => 'projects', 'id' => (string) $project->project_id]],
            ],
        ],
    ], [...$headers, 'Idempotency-Key' => 'plan-create'])->assertCreated();
    $plan = TestPlan::query()->findOrFail($planResponse->json('data.id'));
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    $plan->testCases()->attach($case);

    $runResponse = $this->json('POST', '/api/v1/test-runs', [
        'data' => [
            'type' => 'test-runs',
            'attributes' => [
                'name' => 'Chrome staging',
                'environment' => 'Staging',
                'priority' => 'High',
                'tester_name' => 'API Tester',
            ],
            'relationships' => [
                'test_plan' => ['data' => ['type' => 'test-plans', 'id' => (string) $plan->id]],
                'test_cases' => ['data' => [['type' => 'test-cases', 'id' => (string) $case->id]]],
            ],
        ],
    ], [...$headers, 'Idempotency-Key' => 'run-create'])->assertCreated();
    $runId = $runResponse->json('data.id');

    $started = $this->json('POST', "/api/v1/test-runs/{$runId}/start", [], [
        ...$headers,
        'Idempotency-Key' => 'run-start',
        'If-Match' => $runResponse->headers->get('ETag'),
    ])->assertOk()->assertJsonPath('data.attributes.status', 'In Progress');

    $this->json('PATCH', "/api/v1/test-runs/{$runId}/case-results/{$case->id}", [
        'data' => [
            'type' => 'test-run-case-results',
            'attributes' => ['execution_status' => 'Passed'],
        ],
    ], [...$headers, 'If-Match' => $started->headers->get('ETag')])
        ->assertOk()
        ->assertJsonPath('data.attributes.status', 'Completed');

    $this->json('POST', '/api/v1/issues', [
        'data' => [
            'type' => 'issues',
            'attributes' => [
                'title' => 'Login button overlaps footer',
                'description' => 'Visible at mobile width.',
                'priority' => 'High',
                'severity' => 'Medium',
                'status' => 'Open',
                'reporter' => 'API Tester',
            ],
            'relationships' => [
                'project' => ['data' => ['type' => 'projects', 'id' => (string) $project->project_id]],
                'test_plan' => ['data' => ['type' => 'test-plans', 'id' => (string) $plan->id]],
                'test_run' => ['data' => ['type' => 'test-runs', 'id' => (string) $runId]],
            ],
        ],
    ], [...$headers, 'Idempotency-Key' => 'issue-create'])
        ->assertCreated()
        ->assertJsonPath('data.attributes.severity', 'Medium');
});
