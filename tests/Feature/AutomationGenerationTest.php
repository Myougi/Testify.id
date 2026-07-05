<?php

use App\Ai\Agents\AutomationDefinitionAgent;
use App\Jobs\DispatchAutomationRun;
use App\Jobs\GenerateAutomationDefinition;
use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\Project;
use App\Models\ProjectEnvironment;
use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationGenerationService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('automation.enabled', true);
});

test('valid static generation completes without github dispatch', function (): void {
    Queue::fake();
    [$user, $case] = generationFixture();
    AutomationDefinitionAgent::fake([[
        'definition' => [
            'version' => 1,
            'steps' => [['action' => 'navigate', 'path' => '/login']],
        ],
        'warnings' => [],
    ]])->preventStrayPrompts();
    $service = app(AutomationGenerationService::class);
    $run = $service->dispatch($case, TestRun::ENVIRONMENT_SIT, $user, 'Use the login form.');

    Queue::assertPushed(GenerateAutomationDefinition::class);
    $service->processStatic($run->id);

    $run->refresh();
    $case->refresh()->load('automation');
    expect($run->status)->toBe(AutomationRun::STATUS_COMPLETED)
        ->and($run->generation_source)->toBe(AutomationRun::SOURCE_STATIC)
        ->and($case->automation?->generation_source)->toBe(AutomationRun::SOURCE_STATIC)
        ->and($case->automation?->generation_run_id)->toBe($run->id);
    Queue::assertNotPushed(DispatchAutomationRun::class);
});

test('invalid static output dispatches one agentic fallback for the source hash', function (): void {
    config()->set('automation.agentic_generation.enabled', true);
    Queue::fake();
    [$user, $case] = generationFixture();
    AutomationDefinitionAgent::fake(array_fill(0, 3, [
        'definition' => [
            'version' => 1,
            'steps' => [['action' => 'navigate', 'path' => 'https://attacker.example/login']],
        ],
        'warnings' => [],
    ]))->preventStrayPrompts();
    $service = app(AutomationGenerationService::class);
    $run = $service->dispatch($case, TestRun::ENVIRONMENT_SIT, $user);

    $service->processStatic($run->id);

    expect($run->fresh()->generation_source)->toBe(AutomationRun::SOURCE_AGENTIC)
        ->and($run->fresh()->status)->toBe(AutomationRun::STATUS_QUEUED);
    Queue::assertPushed(DispatchAutomationRun::class, 1);
});

test('static generation failure stores a readable summary when fallback is disabled', function (): void {
    Queue::fake();
    [$user, $case] = generationFixture();
    AutomationDefinitionAgent::fake(array_fill(0, 3, [
        'definition' => [
            'version' => 1,
            'steps' => [['action' => 'click']],
        ],
        'warnings' => [],
    ]))->preventStrayPrompts();
    $service = app(AutomationGenerationService::class);
    $run = $service->dispatch($case, TestRun::ENVIRONMENT_SIT, $user);

    $service->processStatic($run->id);

    expect($run->fresh()->status)->toBe(AutomationRun::STATUS_ERROR)
        ->and($run->fresh()->phase)->toBe(AutomationRun::PHASE_FAILED)
        ->and($run->fresh()->progress_message)->toBe('Step 1 click is missing locator_by.')
        ->and($run->fresh()->failure_message)->toBe('Step 1 click is missing locator_by.')
        ->and($run->fresh()->executions()->firstOrFail()->failure_message)->toBe('Step 1 click is missing locator_by.');
    Queue::assertNotPushed(DispatchAutomationRun::class);
});

test('agentic result is recompiled and saved as an unvalidated browser recorded draft', function (): void {
    config()->set('automation.agentic_generation.enabled', true);
    Queue::fake();
    [$user, $case] = generationFixture();
    $service = app(AutomationGenerationService::class);
    $run = $service->dispatch($case, TestRun::ENVIRONMENT_SIT, $user);
    $run->forceFill(['generation_source' => AutomationRun::SOURCE_AGENTIC])->save();
    $execution = $run->executions()->firstOrFail();

    $service->completeAgentic($execution, [
        'success' => true,
        'definition' => [
            'version' => 1,
            'steps' => [[
                'action' => 'click',
                'locator_by' => 'role',
                'locator_role' => 'button',
                'locator_name' => 'Sign in',
            ]],
        ],
        'warnings' => ['Confirm the post-login assertion.'],
        'duration_ms' => 1200,
    ]);

    $automation = $case->fresh()->automation;
    expect($automation)->not->toBeNull()
        ->and($automation?->status)->toBe('Draft')
        ->and($automation?->generation_source)->toBe(AutomationRun::SOURCE_AGENTIC)
        ->and($automation?->last_validated_at)->toBeNull()
        ->and($automation?->approved_at)->toBeNull()
        ->and($run->fresh()->phase)->toBe(AutomationRun::PHASE_DRAFT_RECORDED)
        ->and($execution->fresh()->status)->toBe(AutomationExecution::STATUS_PASSED);
});

test('agentic result stores one readable failure summary when returned dsl is invalid', function (): void {
    config()->set('automation.agentic_generation.enabled', true);
    Queue::fake();
    [$user, $case] = generationFixture();
    $service = app(AutomationGenerationService::class);
    $run = $service->dispatch($case, TestRun::ENVIRONMENT_SIT, $user);
    $run->forceFill(['generation_source' => AutomationRun::SOURCE_AGENTIC])->save();
    $execution = $run->executions()->firstOrFail();

    $updated = $service->completeAgentic($execution, [
        'success' => true,
        'definition' => [
            'version' => 1,
            'steps' => [['action' => 'click']],
        ],
        'warnings' => [],
        'duration_ms' => 1200,
    ]);

    expect($updated->status)->toBe(AutomationExecution::STATUS_FAILED)
        ->and($updated->failure_message)->toBe('Step 1 click is missing locator_by.')
        ->and($run->fresh()->status)->toBe(AutomationRun::STATUS_FAILED)
        ->and($run->fresh()->progress_message)->toBe('Step 1 click is missing locator_by.')
        ->and($run->fresh()->failure_message)->toBe('Step 1 click is missing locator_by.');
});

/** @return array{User, TestCase} */
function generationFixture(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    ProjectEnvironment::factory()->for($project, 'project')->create([
        'environment' => TestRun::ENVIRONMENT_SIT,
        'base_url' => 'https://sit.example.test',
        'enabled' => true,
    ]);
    $module = TestRepositoryModule::factory()->for($project, 'project')->create();
    $case = TestCase::factory()->for($project, 'project')->for($module, 'module')->create();
    TestCaseStep::factory()->for($case)->create([
        'position' => 1,
        'action' => 'Open the login page and sign in.',
        'expected_result' => 'The dashboard is displayed.',
    ]);

    return [$user, $case];
}
