<?php

namespace Database\Factories;

use App\Models\AutomationExecution;
use App\Models\AutomationHealingAttempt;
use App\Models\TestCaseAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationHealingAttempt>
 */
class AutomationHealingAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_case_automation_id' => TestCaseAutomation::factory(),
            'source_execution_id' => AutomationExecution::factory(),
            'status' => AutomationHealingAttempt::STATUS_HEALING,
            'failed_step' => 1,
            'original_definition' => [
                'version' => 1,
                'steps' => [['action' => 'navigate', 'path' => '/']],
            ],
            'source_hash' => fake()->sha256(),
        ];
    }
}
