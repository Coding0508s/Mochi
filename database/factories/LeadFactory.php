<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'institution_name' => fake()->company(),
            'website_url' => fake()->optional()->url(),
            'address' => fake()->optional()->address(),
            'expected_student_count' => fake()->optional()->numberBetween(10, 300),
            'current_program' => fake()->optional()->word(),
            'status' => 'active',
            'stage' => 'new',
            'owner_user_id' => User::factory(),
            'registered_by_user_id' => null,
            'interest_level' => fake()->numberBetween(1, 5),
            'priority_level' => fake()->numberBetween(1, 3),
            'lead_score' => fake()->numberBetween(0, 100),
        ];
    }
}
