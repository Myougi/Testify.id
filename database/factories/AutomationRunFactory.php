<?php

namespace Database\Factories;

use App\Models\AutomationRun;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRun>
 */
class AutomationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => AutomationRun::KIND_EXECUTION,
            'test_run_id' => TestRun::factory(),
            'requested_by' => User::factory(),
            'environment' => TestRun::ENVIRONMENT_SIT,
            'base_url' => 'https://example.test',
            'attempt' => 1,
            'provider' => 'github_actions',
            'status' => AutomationRun::STATUS_QUEUED,
        ];
    }
}
