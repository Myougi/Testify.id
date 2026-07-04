<?php

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestCaseAutomation;
use App\Models\TestCaseStep;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use App\Services\Automation\AutomationDefinitionService;
use App\Support\Automation\AutomationDefinitionValidator;
use Illuminate\Validation\ValidationException;

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

    $this->actingAs($intruder)
        ->getJson("/api/test-cases/{$case->id}/automation")
        ->assertNotFound();

    $this->actingAs($intruder)
        ->getJson("/api/projects/{$project->project_id}/automation-settings")
        ->assertNotFound();
});

test('automation settings expose the full ordered project environment list', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->getJson("/api/projects/{$project->project_id}/automation-settings")
        ->assertOk();

    expect($response->json('environments'))->toBeArray()
        ->and(array_column($response->json('environments'), 'environment'))->toBe(TestRun::environments());
});
