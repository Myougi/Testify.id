<?php

use App\Http\Controllers\Api\AiGenerationController;
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
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamPageController;
use App\Http\Controllers\TestPlanPageController;
use App\Http\Controllers\TestRepositoryPageController;
use App\Http\Controllers\TestRunPageController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::controller(TeamInvitationController::class)->group(function (): void {
    Route::get('team-invitations/{teamInvitation}', 'show')->name('team-invitations.show');
    Route::post('team-invitations/{teamInvitation}/accept', 'accept')
        ->middleware('auth')
        ->name('team-invitations.accept');
    Route::post('team-invitations/{teamInvitation}/register', 'register')
        ->middleware('guest')
        ->name('team-invitations.register');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardPageController::class, 'index'])->name('dashboard');
    Route::get('teams', TeamPageController::class)->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('teams/{team}/members', [TeamController::class, 'storeMember'])->name('teams.members.store');
    Route::delete('teams/{team}/members/{teamMember}', [TeamController::class, 'destroyMember'])->name('teams.members.destroy');
    Route::post('teams/{team}/project-access', [TeamController::class, 'storeProjectAccess'])->name('teams.project-access.store');
    Route::delete('teams/{team}/project-access/{projectTeamAccess}', [TeamController::class, 'destroyProjectAccess'])->name('teams.project-access.destroy');
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
});

require __DIR__.'/settings.php';
