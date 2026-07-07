<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email' => fake()->safeEmail(),
            'token' => Str::random(40),
            'invited_by_user_id' => User::factory(),
            'invited_user_id' => null,
            'expires_at' => now()->addDays(3),
            'accepted_at' => null,
        ];
    }
}
