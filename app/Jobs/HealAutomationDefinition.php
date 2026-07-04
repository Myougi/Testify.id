<?php

namespace App\Jobs;

use App\Models\AutomationExecution;
use App\Services\Automation\AutomationHealingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HealAutomationDefinition implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $automationExecutionId)
    {
        $this->onQueue('automation');
    }

    public function uniqueId(): string
    {
        return $this->automationExecutionId;
    }

    public function handle(AutomationHealingService $healing): void
    {
        $healing->heal(AutomationExecution::query()->findOrFail($this->automationExecutionId));
    }
}
