<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectEnvironment;
use App\Models\TestRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectEnvironment>
 */
class ProjectEnvironmentFactory extends Factory
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
            'environment' => fake()->randomElement(TestRun::environments()),
            'base_url' => fake()->url(),
            'enabled' => true,
        ];
    }
}
