<?php

namespace Database\Factories;

use App\Models\TestRun;
use App\Models\TestPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestRun>
 */
class TestRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_plan_id' => TestPlan::factory(),
            'name' => fake()->sentence(3),
            'environment' => fake()->randomElement(TestRun::environments()),
            'priority' => fake()->randomElement(TestRun::priorities()),
            'description' => fake()->optional()->sentence(),
            'tester_name' => fake()->name(),
            'status' => TestRun::STATUS_NEW,
        ];
    }
}
