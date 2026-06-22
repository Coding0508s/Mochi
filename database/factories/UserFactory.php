<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'employee_empno' => null,
            'email_verified_at' => now(),
            'password' => 'password',
            'is_admin' => false,
            'can_view_all_institutions' => false,
            'can_manage_store_inventory' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    public function coachTeamLead(): static
    {
        return $this->state(fn (array $attributes) => [
            'team' => 'COACH',
            'is_coach_team_lead' => true,
        ]);
    }

    public function deputyAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
            'is_deputy_admin' => true,
        ]);
    }

    public function canViewAllInstitutions(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_view_all_institutions' => true,
        ]);
    }
}
