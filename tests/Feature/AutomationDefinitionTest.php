<?php

use App\Ai\Agents\AutomationDefinitionAgent;
use App\Ai\Agents\AutomationHealingAgent;
use App\Models\AutomationArtifact;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\ProjectEnvironment;
use App\Models\TestCase;
use App\Models\TestCaseAutomation;
use App\Models\TestCaseStep;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Services\Automation\AutomationHealingService;
use App\Services\Automation\AutomationResultService;
use App\Support\Automation\AutomationDefinitionValidator;
use App\Support\Automation\AutomationStepCompiler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase as FeatureTestCase;

test('safe automation definitions accept allowlisted actions and reject executable input', function (): void {
    $validator = app(AutomationDefinitionValidator::class);
    $definition = $validator->validate([
        'version' => 1,
        'steps' => [
            ['action' => 'navigate', 'path' => '/login'],
            ['action' => 'fill', 'locator' => ['by' => 'label', 'value' => 'Email'], 'secret' => ['profile' => 'user', 'field' => 'email']],
            ['action' => 'click', 'locator' => ['by' => 'role', 'role' => 'button', 'name' => 'Login']],
            ['action' => 'assert_url', 'path' => '/dashboard'],
        ],
    ], ['user' => ['email']]);

    expect($definition['steps'])->toHaveCount(4);

    expect(fn () => $validator->validate([
        'version' => 1,
        'steps' => [['action' => 'navigate', 'path' => 'https://attacker.example']],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $validator->validate([
        'version' => 1,
        'steps' => [['action' => 'javascript', 'code' => 'process.exit(1)']],
    ]))->toThrow(ValidationException::class);
});

test('generated playwright steps compile to exact action fields', function (): void {
    $compiler = app(AutomationStepCompiler::class);
    $definition = $compiler->compileDefinition([
        'version' => 1,
        'steps' => [[
            'action' => 'click',
            'locator_by' => 'role',
            'locator_role' => 'button',
            'locator_name' => 'Sign in',
            'literal_value' => 'unsupported extra from the model',
        ]],
    ]);

    expect($definition['steps'][0])->toBe([
        'action' => 'click',
        'locator' => ['by' => 'role', 'role' => 'button', 'name' => 'Sign in'],
    ]);
});

test('automation ai agents use the configured timeout', function (): void {
    config()->set('automation.ai.timeout_seconds', 180);
    config()->set('automation.ai_healing.timeout_seconds', 180);

    expect(AutomationDefinitionAgent::make(context: [])->timeout())->toBe(180)
        ->and(AutomationHealingAgent::make(context: [])->timeout())->toBe(180);
});

test('automation generation corrects malformed structured output once', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    TestCaseStep::factory()->for($case)->create([
        'position' => 1,
        'action' => 'Click Sign in.',
        'expected_result' => 'Dashboard is displayed.',
    ]);

    AutomationDefinitionAgent::fake([
        [
            'definition' => [
                'version' => 1,
                'steps' => [['action' => 'click', 'locator_by' => 'role']],
            ],
            'warnings' => [],
        ],
        [
            'definition' => [
                'version' => 1,
                'steps' => [[
                    'action' => 'click',
                    'locator_by' => 'role',
                    'locator_role' => 'button',
                    'locator_name' => 'Sign in',
                ]],
            ],
            'warnings' => ['The locator must be confirmed in browser validation.'],
        ],
    ])->preventStrayPrompts();

    $result = app(AutomationDefinitionService::class)->generate($case);

    expect($result['automation']->definition['steps'][0])->toBe([
        'action' => 'click',
        'locator' => ['by' => 'role', 'role' => 'button', 'name' => 'Sign in'],
    ])->and($result['warnings'])->toBe(['The locator must be confirmed in browser validation.']);
});

test('automation generation corrects validator errors once', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    TestCaseStep::factory()->for($case)->create([
        'position' => 1,
        'action' => 'Open the login page.',
        'expected_result' => 'The login page is displayed.',
    ]);

    AutomationDefinitionAgent::fake([
        [
            'definition' => [
                'version' => 1,
                'steps' => [['action' => 'navigate', 'path' => 'https://attacker.example/login']],
            ],
            'warnings' => [],
        ],
        [
            'definition' => [
                'version' => 1,
                'steps' => [['action' => 'navigate', 'path' => '/login']],
            ],
            'warnings' => [],
        ],
    ])->preventStrayPrompts();

    $result = app(AutomationDefinitionService::class)->generate($case);

    expect($result['automation']->definition['steps'])->toBe([
        ['action' => 'navigate', 'path' => '/login'],
    ]);
});

test('failed validation can heal revalidate and become ready automatically', function (): void {
    config()->set('automation.enabled', true);
    config()->set('automation.ai_healing.enabled', true);
    config()->set('automation.github', [
        'owner' => 'testify',
        'repository' => 'testify',
        'workflow' => 'automated-test-run.yml',
        'ref' => 'main',
        'token' => 'github-token',
        'api_url' => 'https://api.github.com',
    ]);
    config()->set('automation.runner.shared_secret', 'runner-secret');
    Storage::fake('automation-artifacts');
    config()->set('filesystems.automation_artifacts_disk', 'automation-artifacts');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    ProjectEnvironment::factory()->for($project, 'project')->create([
        'environment' => TestRun::ENVIRONMENT_SIT,
        'base_url' => 'https://sit.example.test',
        'enabled' => true,
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
        'automation_status' => TestCase::AUTOMATION_AUTOMATED,
    ]);
    TestCaseStep::factory()->for($case)->create([
        'position' => 1,
        'action' => 'Click Sign in.',
        'expected_result' => 'Dashboard is displayed.',
    ]);
    $definition = [
        'version' => 1,
        'steps' => [[
            'action' => 'click',
            'locator' => ['by' => 'text', 'value' => 'Login now'],
        ]],
    ];
    $automation = app(AutomationDefinitionService::class)->save($case, $definition);
    $sourceRun = AutomationRun::factory()->for($user, 'requester')->create([
        'kind' => AutomationRun::KIND_VALIDATION,
        'test_run_id' => null,
        'environment' => TestRun::ENVIRONMENT_SIT,
        'base_url' => 'https://sit.example.test',
        'status' => AutomationRun::STATUS_FAILED,
    ]);
    $execution = AutomationExecution::factory()
        ->for($sourceRun, 'automationRun')
        ->for($case)
        ->for($automation, 'automation')
        ->create([
            'definition_snapshot' => $definition,
            'status' => AutomationExecution::STATUS_BLOCKED,
            'failure_type' => 'automation',
            'failure_code' => 'locator_not_found',
            'failure_message' => 'Login now was not found.',
            'failed_step' => 1,
        ]);
    $diagnosticPath = "automation/{$sourceRun->id}/{$execution->id}/diagnostic.json";
    Storage::disk('automation-artifacts')->put($diagnosticPath, json_encode([
        'relative_url' => '/login',
        'page_title' => 'Login',
        'aria_snapshot' => '- button "Sign in"',
        'visible_candidates' => [['tag' => 'button', 'role' => 'button', 'text' => 'Sign in']],
    ], JSON_THROW_ON_ERROR));
    $execution->artifacts()->create([
        'type' => AutomationArtifact::TYPE_DIAGNOSTIC,
        'disk' => 'automation-artifacts',
        'path' => $diagnosticPath,
        'original_name' => 'diagnostic.json',
        'mime_type' => 'application/json',
        'size' => Storage::disk('automation-artifacts')->size($diagnosticPath),
        'sha256' => hash('sha256', Storage::disk('automation-artifacts')->get($diagnosticPath)),
        'uploaded_at' => now(),
    ]);
    AutomationHealingAgent::fake([[
        'replacement_step' => [
            'action' => 'click',
            'locator_by' => 'role',
            'locator_role' => 'button',
            'locator_name' => 'Sign in',
        ],
        'rationale' => 'The browser evidence exposes Sign in as a uniquely named button.',
        'confidence' => 96,
    ]])->preventStrayPrompts();

    $attempt = app(AutomationHealingService::class)->heal($execution);
    $revalidationExecution = $attempt?->revalidationRun?->executions()->firstOrFail();
    app(AutomationResultService::class)->completeExecution($revalidationExecution, [
        'status' => AutomationExecution::STATUS_PASSED,
        'duration_ms' => 400,
    ]);

    expect($automation->fresh()->status)->toBe(TestCaseAutomation::STATUS_READY)
        ->and($automation->fresh()->approval_source)->toBe(TestCaseAutomation::APPROVAL_AI_HEALED)
        ->and($attempt?->fresh()->status)->toBe('Applied')
        ->and($automation->fresh()->definition['steps'][0]['locator'])->toBe([
            'by' => 'role',
            'role' => 'button',
            'name' => 'Sign in',
        ]);
});

test('credential and infrastructure failures never trigger ai healing', function (string $failureCode): void {
    config()->set('automation.ai_healing.enabled', true);
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    TestCaseStep::factory()->for($case)->create(['position' => 1]);
    $automation = app(AutomationDefinitionService::class)->save($case, [
        'version' => 1,
        'steps' => [['action' => 'navigate', 'path' => '/']],
    ]);
    $run = AutomationRun::factory()->for($user, 'requester')->create([
        'kind' => AutomationRun::KIND_VALIDATION,
        'test_run_id' => null,
    ]);
    $execution = AutomationExecution::factory()
        ->for($run, 'automationRun')
        ->for($case)
        ->for($automation, 'automation')
        ->create([
            'definition_snapshot' => $automation->definition,
            'status' => AutomationExecution::STATUS_BLOCKED,
            'failure_code' => $failureCode,
            'failed_step' => 1,
        ]);

    expect(app(AutomationHealingService::class)->queueIfEligible($execution))->toBeFalse();
    Queue::assertNothingPushed();
})->with(['credential_error', 'infrastructure_error']);

test('approval requires a current successful non production validation', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create([
        'automation_status' => TestCase::AUTOMATION_AUTOMATED,
    ]);
    TestCaseStep::factory()->for($case)->create(['position' => 1]);
    $service = app(AutomationDefinitionService::class);
    $automation = $service->save($case, [
        'version' => 1,
        'steps' => [['action' => 'navigate', 'path' => '/']],
    ]);

    expect(fn () => $service->approve($automation, $user))->toThrow(ValidationException::class);

    $service->markValidationResult($automation, TestRun::ENVIRONMENT_SIT, true, 'Passed in Chromium.');
    $approved = $service->approve($automation->fresh(), $user);

    expect($approved->status)->toBe(TestCaseAutomation::STATUS_READY)
        ->and($approved->approved_by)->toBe($user->id);

    $case->update(['title' => 'Changed after approval']);
    $service->invalidateIfChanged($case->fresh(['automation', 'steps']));

    expect($automation->fresh()->status)->toBe(TestCaseAutomation::STATUS_DRAFT)
        ->and($automation->fresh()->approved_at)->toBeNull();
});

test('automation resources do not leak across project owners', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();

    actingAsUser($this, $intruder)
        ->getJson("/api/test-cases/{$case->id}/automation")
        ->assertNotFound();

    actingAsUser($this, $intruder)
        ->getJson("/api/test-cases/{$case->id}/automation/healing-attempts")
        ->assertNotFound();

    actingAsUser($this, $intruder)
        ->getJson("/api/projects/{$project->project_id}/automation-settings")
        ->assertNotFound();
});

test('automation settings expose the full ordered project environment list', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = actingAsUser($this, $user)
        ->getJson("/api/projects/{$project->project_id}/automation-settings")
        ->assertOk();

    expect($response->json('environments'))->toBeArray()
        ->and(array_column($response->json('environments'), 'environment'))->toBe(TestRun::environments());
});

function actingAsUser(FeatureTestCase $testCase, User $user): FeatureTestCase
{
    return $testCase->actingAs($user);
}
