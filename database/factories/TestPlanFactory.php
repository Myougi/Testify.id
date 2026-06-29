<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestPlan>
 */
class TestPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-30 days', '+15 days');
        $dueDate = fake()->optional(0.8)->dateTimeBetween($startDate, '+45 days');

        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'scope' => fake()->optional()->paragraph(),
            'strategy' => fake()->optional()->paragraph(),
            'criteria' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TestPlan::statuses()),
            'priority' => fake()->randomElement(TestPlan::priorities()),
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'release_version' => fake()->optional()->bothify('v#.#.#-beta'),
            'tester' => fake()->optional()->safeEmail(),
        ];
    }
}
