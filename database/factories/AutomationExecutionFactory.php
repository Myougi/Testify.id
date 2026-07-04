<?php

namespace Database\Factories;

use App\Models\AutomationExecution;
use App\Models\AutomationRun;
use App\Models\TestCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationExecution>
 */
class AutomationExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_run_id' => AutomationRun::factory(),
            'test_case_id' => TestCase::factory(),
            'definition_snapshot' => [
                'version' => 1,
                'steps' => [['action' => 'navigate', 'path' => '/']],
            ],
            'status' => AutomationExecution::STATUS_QUEUED,
        ];
    }
}
