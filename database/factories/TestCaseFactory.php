<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestRepositoryModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCase>
 */
class TestCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::factory();

        return [
            'project_id' => $project,
            'module_id' => TestRepositoryModule::factory()->state([
                'project_id' => $project,
            ]),
            'case_key' => strtoupper(fake()->bothify('TC-???-##')),
            'title' => fake()->sentence(4),
            'priority' => fake()->randomElement(TestCase::priorities()),
            'automation_status' => fake()->randomElement(TestCase::automationStatuses()),
            'test_behavior' => fake()->randomElement(TestCase::behaviors()),
            'test_type' => fake()->randomElement(TestCase::types()),
            'preconditions' => fake()->optional()->paragraph(),
        ];
    }
}
