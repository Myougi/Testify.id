<?php

use App\Http\Controllers\Api\Runner\V1\AutomationRunController as RunnerAutomationRunController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\AutomationRunController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\RepositoryModuleController;
use App\Http\Controllers\Api\V1\TestCaseController;
use App\Http\Controllers\Api\V1\TestPlanCaseRelationshipController;
use App\Http\Controllers\Api\V1\TestPlanController;
use App\Http\Controllers\Api\V1\TestRunController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('runner/v1')
    ->middleware(['api.request-id', 'automation.runner', 'throttle:automation-runner'])
    ->group(function (): void {
        Route::get('automation-runs/{automationRun}', [RunnerAutomationRunController::class, 'show']);
        Route::post('automation-runs/{automationRun}/start', [RunnerAutomationRunController::class, 'start']);
        Route::post('automation-runs/{automationRun}/progress', [RunnerAutomationRunController::class, 'progress']);
        Route::post('automation-runs/{automationRun}/executions/{execution}/generation-result', [RunnerAutomationRunController::class, 'generationResult']);
        Route::post('automation-runs/{automationRun}/executions/{execution}/result', [RunnerAutomationRunController::class, 'result']);
        Route::post('automation-runs/{automationRun}/executions/{execution}/artifacts', [RunnerAutomationRunController::class, 'reserveArtifact']);
        Route::post('automation-runs/{automationRun}/executions/{execution}/artifacts/{artifact}/finalize', [RunnerAutomationRunController::class, 'finalizeArtifact']);
    });

Route::prefix('v1')->middleware(['api.request-id', 'api.json', 'api.sparse-fields'])->group(function (): void {
    Route::post('auth/tokens', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])
            ->middleware(['api.ability:tokens:write', 'throttle:api-write']);

        Route::get('users/me', [CurrentUserController::class, 'show'])
            ->middleware(['api.ability:profile:read', 'throttle:api-read']);
        Route::patch('users/me', [CurrentUserController::class, 'update'])
            ->middleware(['api.ability:profile:write', 'throttle:api-write']);

        Route::get('tokens', [TokenController::class, 'index'])
            ->middleware(['api.ability:tokens:read', 'throttle:api-read']);
        Route::delete('tokens/{token}', [TokenController::class, 'destroy'])
            ->middleware(['api.ability:tokens:write', 'throttle:api-write']);

        Route::get('openapi', OpenApiController::class)
            ->middleware(['api.ability:docs:read', 'throttle:api-read']);

        Route::get('projects', [ProjectController::class, 'index'])->middleware(['api.ability:projects:read', 'throttle:api-read']);
        Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware(['api.ability:projects:read', 'throttle:api-read']);
        Route::get('projects/{project}/automation-settings', [AutomationController::class, 'settings'])->middleware(['api.ability:automations:read', 'throttle:api-read']);
        Route::patch('projects/{project}/automation-settings', [AutomationController::class, 'updateSettings'])->middleware(['api.ability:automations:write', 'throttle:api-write']);
        Route::post('projects', [ProjectController::class, 'store'])->middleware(['api.ability:projects:write', 'throttle:api-write']);
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware(['api.ability:projects:write', 'throttle:api-write']);
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware(['api.ability:projects:write', 'throttle:api-write']);

        Route::get('repository-modules', [RepositoryModuleController::class, 'index'])->middleware(['api.ability:repository-modules:read', 'throttle:api-read']);
        Route::get('repository-modules/{repositoryModule}', [RepositoryModuleController::class, 'show'])->middleware(['api.ability:repository-modules:read', 'throttle:api-read']);
        Route::post('repository-modules', [RepositoryModuleController::class, 'store'])->middleware(['api.ability:repository-modules:write', 'throttle:api-write']);
        Route::patch('repository-modules/{repositoryModule}', [RepositoryModuleController::class, 'update'])->middleware(['api.ability:repository-modules:write', 'throttle:api-write']);
        Route::delete('repository-modules/{repositoryModule}', [RepositoryModuleController::class, 'destroy'])->middleware(['api.ability:repository-modules:write', 'throttle:api-write']);

        Route::get('test-plans', [TestPlanController::class, 'index'])->middleware(['api.ability:test-plans:read', 'throttle:api-read']);
        Route::get('test-plans/{testPlan}', [TestPlanController::class, 'show'])->middleware(['api.ability:test-plans:read', 'throttle:api-read']);
        Route::post('test-plans', [TestPlanController::class, 'store'])->middleware(['api.ability:test-plans:write', 'throttle:api-write']);
        Route::patch('test-plans/{testPlan}', [TestPlanController::class, 'update'])->middleware(['api.ability:test-plans:write', 'throttle:api-write']);
        Route::delete('test-plans/{testPlan}', [TestPlanController::class, 'destroy'])->middleware(['api.ability:test-plans:write', 'throttle:api-write']);
        Route::get('test-plans/{testPlan}/test-cases', [TestPlanCaseRelationshipController::class, 'index'])->middleware(['api.ability:test-plans:read', 'throttle:api-read']);
        Route::post('test-plans/{testPlan}/relationships/test-cases', [TestPlanCaseRelationshipController::class, 'store'])->middleware(['api.ability:test-plans:write', 'throttle:api-write']);
        Route::delete('test-plans/{testPlan}/relationships/test-cases/{testCase}', [TestPlanCaseRelationshipController::class, 'destroy'])->middleware(['api.ability:test-plans:write', 'throttle:api-write']);

        Route::get('test-cases', [TestCaseController::class, 'index'])->middleware(['api.ability:test-cases:read', 'throttle:api-read']);
        Route::get('test-cases/{testCase}', [TestCaseController::class, 'show'])->middleware(['api.ability:test-cases:read', 'throttle:api-read']);
        Route::post('test-cases', [TestCaseController::class, 'store'])->middleware(['api.ability:test-cases:write', 'throttle:api-write']);
        Route::patch('test-cases/{testCase}', [TestCaseController::class, 'update'])->middleware(['api.ability:test-cases:write', 'throttle:api-write']);
        Route::delete('test-cases/{testCase}', [TestCaseController::class, 'destroy'])->middleware(['api.ability:test-cases:write', 'throttle:api-write']);

        Route::get('test-runs', [TestRunController::class, 'index'])->middleware(['api.ability:test-runs:read', 'throttle:api-read']);
        Route::get('test-runs/{testRun}', [TestRunController::class, 'show'])->middleware(['api.ability:test-runs:read', 'throttle:api-read']);
        Route::post('test-runs', [TestRunController::class, 'store'])->middleware(['api.ability:test-runs:write', 'throttle:api-write']);
        Route::patch('test-runs/{testRun}', [TestRunController::class, 'update'])->middleware(['api.ability:test-runs:write', 'throttle:api-write']);
        Route::delete('test-runs/{testRun}', [TestRunController::class, 'destroy'])->middleware(['api.ability:test-runs:write', 'throttle:api-write']);
        Route::post('test-runs/{testRun}/start', [TestRunController::class, 'start'])->middleware(['api.ability:test-runs:write', 'throttle:api-write']);
        Route::patch('test-runs/{testRun}/case-results/{testCase}', [TestRunController::class, 'updateCaseResult'])->middleware(['api.ability:test-runs:write', 'throttle:api-write']);

        Route::get('test-cases/{testCase}/automation', [AutomationController::class, 'show'])->middleware(['api.ability:automations:read', 'throttle:api-read']);
        Route::get('test-cases/{testCase}/automation/healing-attempts', [AutomationController::class, 'healingHistory'])->middleware(['api.ability:automations:read', 'throttle:api-read']);
        Route::post('test-cases/{testCase}/automation/generate', [AutomationController::class, 'generate'])->middleware(['api.ability:automations:write', 'throttle:api-write']);
        Route::patch('test-cases/{testCase}/automation', [AutomationController::class, 'update'])->middleware(['api.ability:automations:write', 'throttle:api-write']);
        Route::post('test-cases/{testCase}/automation/validate', [AutomationController::class, 'validateDefinition'])->middleware(['api.ability:automations:write', 'throttle:api-write']);
        Route::post('test-cases/{testCase}/automation/approve', [AutomationController::class, 'approve'])->middleware(['api.ability:automations:write', 'throttle:api-write']);
        Route::get('automation-runs/{automationRun}', [AutomationRunController::class, 'show'])->middleware(['api.ability:automations:read', 'throttle:api-read']);
        Route::get('test-runs/{testRun}/automation-runs', [AutomationRunController::class, 'index'])->middleware(['api.ability:automations:read', 'throttle:api-read']);
        Route::post('test-runs/{testRun}/automation-runs/retry', [AutomationRunController::class, 'retry'])->middleware(['api.ability:automations:write', 'throttle:api-write']);
        Route::post('automation-executions/{execution}/issue-draft', [AutomationController::class, 'issueDraft'])->middleware(['api.ability:automations:write', 'throttle:api-write']);

        Route::get('issues', [IssueController::class, 'index'])->middleware(['api.ability:issues:read', 'throttle:api-read']);
        Route::get('issues/{issue}', [IssueController::class, 'show'])->middleware(['api.ability:issues:read', 'throttle:api-read']);
        Route::post('issues', [IssueController::class, 'store'])->middleware(['api.ability:issues:write', 'throttle:api-write']);
        Route::patch('issues/{issue}', [IssueController::class, 'update'])->middleware(['api.ability:issues:write', 'throttle:api-write']);
        Route::delete('issues/{issue}', [IssueController::class, 'destroy'])->middleware(['api.ability:issues:write', 'throttle:api-write']);
    });
});
