<?php

use App\Models\AutomationExecution;
use App\Models\AutomationHealingAttempt;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Support\Api\V1\JsonApi;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase as FeatureTestCase;

const AUTOMATION_JSON_API = 'application/vnd.api+json';

test('api v1 automation resources enforce abilities ownership and etags', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $foreignProject = Project::factory()->for($other)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
        'automation_status' => TestCase::AUTOMATION_AUTOMATED,
    ]);
    $automation = app(AutomationDefinitionService::class)->save($case, [
        'version' => 1,
        'steps' => [['action' => 'navigate', 'path' => '/']],
    ]);
    $sourceRun = AutomationRun::factory()->for($user, 'requester')->create();
    $sourceExecution = AutomationExecution::factory()
        ->for($sourceRun, 'automationRun')
        ->for($case)
        ->for($automation, 'automation')
        ->create();
    AutomationHealingAttempt::factory()
        ->for($automation, 'automation')
        ->for($sourceExecution, 'sourceExecution')
        ->create([
            'source_hash' => $automation->source_hash,
            'status' => AutomationHealingAttempt::STATUS_FAILED,
        ]);
    Sanctum::actingAs($user, ['automations:read']);

    apiGetJson($this, "/api/v1/projects/{$project->project_id}/automation-settings", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertOk()
        ->assertHeader('ETag')
        ->assertJsonPath('data.type', 'project-automation-settings');

    apiGetJson($this, "/api/v1/test-cases/{$case->id}/automation", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertOk()
        ->assertJsonPath('data.attributes.status', 'Draft');

    apiGetJson($this, "/api/v1/test-cases/{$case->id}/automation/healing-attempts", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertOk()
        ->assertJsonPath('data.0.type', 'automation-healing-attempts')
        ->assertJsonPath('data.0.attributes.status', AutomationHealingAttempt::STATUS_FAILED);

    apiGetJson($this, "/api/v1/projects/{$foreignProject->project_id}/automation-settings", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertNotFound();

    $payload = automationSettingsPayload();
    apiJson($this, 'PATCH', "/api/v1/projects/{$project->project_id}/automation-settings", $payload, [
        'Accept' => AUTOMATION_JSON_API,
        'Content-Type' => AUTOMATION_JSON_API,
        'If-Match' => JsonApi::etag($project),
    ])->assertForbidden();

    Sanctum::actingAs($user, ['automations:read', 'automations:write']);
    apiJson($this, 'PATCH', "/api/v1/projects/{$project->project_id}/automation-settings", $payload, [
        'Accept' => AUTOMATION_JSON_API,
        'Content-Type' => AUTOMATION_JSON_API,
        'If-Match' => JsonApi::etag($project),
    ])->assertOk()
        ->assertJsonPath('data.attributes.environments.1.environment', TestRun::ENVIRONMENT_SIT)
        ->assertJsonPath('data.attributes.environments.1.base_url', 'https://sit.example.test');
});

/** @return array<string, mixed> */
function automationSettingsPayload(): array
{
    return [
        'data' => [
            'type' => 'project-automation-settings',
            'attributes' => [
                'environments' => collect(TestRun::environments())->map(fn (string $environment): array => [
                    'environment' => $environment,
                    'base_url' => $environment === TestRun::ENVIRONMENT_SIT ? 'https://sit.example.test' : '',
                    'enabled' => $environment === TestRun::ENVIRONMENT_SIT,
                ])->all(),
                'credential_profiles' => [[
                    'environment' => TestRun::ENVIRONMENT_SIT,
                    'name' => 'Standard user',
                    'key' => 'standard_user',
                    'fields' => ['email', 'password'],
                ]],
            ],
        ],
    ];
}

function apiGetJson(FeatureTestCase $testCase, string $uri, array $headers = []): TestResponse
{
    return $testCase->getJson($uri, $headers);
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function apiJson(FeatureTestCase $testCase, string $method, string $uri, array $data = [], array $headers = []): TestResponse
{
    return $testCase->json($method, $uri, $data, $headers);
}
