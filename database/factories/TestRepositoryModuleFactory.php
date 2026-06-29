<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TestRepositoryModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestRepositoryModule>
 */
class TestRepositoryModuleFactory extends Factory
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
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'key_prefix' => strtoupper(fake()->bothify('???')),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
