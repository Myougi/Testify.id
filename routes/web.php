<?php

use App\Http\Controllers\Api\AiGenerationController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\Api\TestCaseController;
use App\Http\Controllers\Api\TestPlanController;
use App\Http\Controllers\Api\TestRepositoryModuleController;
use App\Http\Controllers\Api\TestRunController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\IssueAttachmentController;
use App\Http\Controllers\IssuePageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportPageController;
use App\Http\Controllers\TestPlanPageController;
use App\Http\Controllers\TestRepositoryPageController;
use App\Http\Controllers\TestRunPageController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardPageController::class, 'index'])->name('dashboard');
    Route::get('projects/{project}/workspace', [ProjectController::class, 'workspace'])
        ->name('projects.workspace');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::controller(TestPlanPageController::class)->group(function (): void {
        Route::get('test-plans', 'index')->name('test-plans.index');
        Route::get('test-plans/{testPlan}/export', 'export')
            ->name('test-plans.export')
            ->middleware('throttle:api-read');
        Route::get('test-plans/{testPlan}', 'show')->name('test-plans.show');
    });
    Route::get('test-repositories', TestRepositoryPageController::class)->name('test-repositories.index');
    Route::get('test-repositories/test-cases/create', [TestRepositoryPageController::class, 'create'])
        ->name('test-repositories.test-cases.create');
    Route::get('test-repositories/test-cases/{testCase}/edit', [TestRepositoryPageController::class, 'edit'])
        ->name('test-repositories.test-cases.edit');
    Route::controller(TestRunPageController::class)->group(function (): void {
        Route::get('test-executions', 'index')->name('test-executions.index');
        Route::get('test-executions/{testRun}', 'show')->name('test-executions.show');
    });
    Route::controller(IssuePageController::class)->group(function (): void {
        Route::get('issues', 'index')->name('issues.index');
        Route::get('issues/{issue}', 'show')->name('issues.show');
    });
    Route::get('issues/{issue}/image', IssueAttachmentController::class)
        ->name('issues.image');
    Route::controller(ReportPageController::class)->group(function (): void {
        Route::get('reports', 'index')->name('reports.index');
        Route::post('reports/export', 'export')
            ->name('reports.export')
            ->middleware('throttle:api-write');
    });
    Route::prefix('api')->name('api.')->group(function (): void {
        Route::post('ai/generate', [AiGenerationController::class, 'store'])
            ->name('ai.generate')
            ->middleware('throttle:api-write');
        Route::get('projects/{project}/automation-settings', [AutomationController::class, 'settings']);
        Route::put('projects/{project}/automation-settings', [AutomationController::class, 'updateSettings']);
        Route::get('test-cases/{testCase}/automation', [AutomationController::class, 'show']);
        Route::put('test-cases/{testCase}/automation', [AutomationController::class, 'update']);
        Route::post('test-cases/{testCase}/automation/generate', [AutomationController::class, 'generate']);
        Route::post('test-cases/{testCase}/automation/validate', [AutomationController::class, 'validateDefinition']);
        Route::post('test-cases/{testCase}/automation/approve', [AutomationController::class, 'approve']);
        Route::get('test-runs/{testRun}/automation-runs', [AutomationController::class, 'runs']);
        Route::post('test-runs/{testRun}/automation-runs/retry', [AutomationController::class, 'retry']);
        Route::post('automation-executions/{execution}/issue-draft', [AutomationController::class, 'issueDraft']);
        Route::apiResource('issues', IssueController::class)->only([
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ]);
        Route::apiResource('test-repository-modules', TestRepositoryModuleController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['test-repository-modules' => 'module']);
        Route::post('test-cases/bulk', [TestCaseController::class, 'bulkStore'])
            ->name('test-cases.bulk-store');
        Route::apiResource('test-cases', TestCaseController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy'])
            ->parameters(['test-cases' => 'testCase']);
        Route::apiResource('test-plans', TestPlanController::class)->only([
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ]);
        Route::apiResource('test-runs', TestRunController::class)->only([
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ]);
        Route::patch('test-runs/{testRun}/start', [TestRunController::class, 'start'])
            ->name('test-runs.start');
        Route::patch('test-runs/{testRun}/test-cases/{testCase}/status', [TestRunController::class, 'updateCaseStatus'])
            ->name('test-runs.test-cases.update-status');
        Route::get('test-plans/{test_plan}/available-test-cases', [TestPlanController::class, 'availableTestCases'])
            ->name('test-plans.available-test-cases');
        Route::post('test-plans/{test_plan}/test-cases', [TestPlanController::class, 'attachTestCases'])
            ->name('test-plans.attach-test-cases');
        Route::delete('test-plans/{test_plan}/test-cases/{testCase}', [TestPlanController::class, 'detachTestCase'])
            ->name('test-plans.detach-test-case');
    });
    Route::get('automation-artifacts/{artifact}', [AutomationController::class, 'artifact'])
        ->name('automation-artifacts.download');
});

require __DIR__.'/settings.php';
