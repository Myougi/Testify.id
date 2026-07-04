<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Support\Api\V1\JsonApi;
use Laravel\Sanctum\Sanctum;

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
    app(AutomationDefinitionService::class)->save($case, [
        'version' => 1,
        'steps' => [['action' => 'navigate', 'path' => '/']],
    ]);
    Sanctum::actingAs($user, ['automations:read']);

    $this->getJson("/api/v1/projects/{$project->project_id}/automation-settings", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertOk()
        ->assertHeader('ETag')
        ->assertJsonPath('data.type', 'project-automation-settings');

    $this->getJson("/api/v1/test-cases/{$case->id}/automation", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertOk()
        ->assertJsonPath('data.attributes.status', 'Draft');

    $this->getJson("/api/v1/projects/{$foreignProject->project_id}/automation-settings", [
        'Accept' => AUTOMATION_JSON_API,
    ])->assertNotFound();

    $payload = automationSettingsPayload();
    $this->json('PATCH', "/api/v1/projects/{$project->project_id}/automation-settings", $payload, [
        'Accept' => AUTOMATION_JSON_API,
        'Content-Type' => AUTOMATION_JSON_API,
        'If-Match' => JsonApi::etag($project),
    ])->assertForbidden();

    Sanctum::actingAs($user, ['automations:read', 'automations:write']);
    $this->json('PATCH', "/api/v1/projects/{$project->project_id}/automation-settings", $payload, [
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
