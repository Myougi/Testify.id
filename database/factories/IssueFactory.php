<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'test_plan_id' => null,
            'test_run_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->sentence(8),
            'expected_result' => fake()->optional()->sentence(),
            'actual_result' => fake()->optional()->sentence(),
            'priority' => fake()->randomElement(Issue::priorities()),
            'severity' => fake()->randomElement(Issue::severities()),
            'status' => fake()->randomElement([
                Issue::STATUS_OPEN,
                Issue::STATUS_IN_INVESTIGATION,
                Issue::STATUS_IN_TESTING,
                Issue::STATUS_DEFERRED,
            ]),
            'reporter' => fake()->name(),
            'assignee' => fake()->optional()->name(),
            'environment' => fake()->optional()->randomElement(Issue::environments()),
            'defect_type' => fake()->optional()->randomElement(Issue::defectTypes()),
            'closed_at' => null,
        ];
    }
}
