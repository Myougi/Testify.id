<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectCredentialProfile;
use App\Models\TestRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectCredentialProfile>
 */
class ProjectCredentialProfileFactory extends Factory
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
            'name' => 'Default test user',
            'key' => 'default_user',
            'fields' => ['email', 'password'],
        ];
    }
}
