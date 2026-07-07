<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTeamAccess;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTeamAccess>
 */
class ProjectTeamAccessFactory extends Factory
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
            'team_id' => Team::factory(),
            'granted_by_user_id' => User::factory(),
        ];
    }
}
