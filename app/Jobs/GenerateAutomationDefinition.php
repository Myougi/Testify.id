<?php

namespace App\Jobs;

use App\Services\Automation\AutomationGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAutomationDefinition implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $automationRunId)
    {
        $this->onQueue('automation');
    }

    /**
     * Execute the job.
     */
    public function handle(AutomationGenerationService $generation): void
    {
        $generation->processStatic($this->automationRunId);
    }

    public function failed(Throwable $exception): void
    {
        app(AutomationGenerationService::class)->fail($this->automationRunId, $exception);
    }
}
