<?php

namespace App\Jobs;

use App\Models\AutomationRun;
use App\Services\Automation\AutomationResultService;
use App\Services\Automation\GitHubWorkflowDispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchAutomationRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public string $automationRunId)
    {
        $this->onQueue('automation');
    }

    public function uniqueId(): string
    {
        return $this->automationRunId;
    }

    public function handle(GitHubWorkflowDispatcher $dispatcher): void
    {
        $run = AutomationRun::query()->findOrFail($this->automationRunId);
        $run->forceFill(['status' => AutomationRun::STATUS_DISPATCHING])->save();
        $dispatcher->dispatch($run);
        $run->forceFill(['dispatched_at' => now()])->save();
    }

    public function failed(Throwable $exception): void
    {
        app(AutomationResultService::class)->failDispatch(
            AutomationRun::query()->find($this->automationRunId),
            $exception->getMessage(),
        );
    }
}
