<?php

namespace Database\Factories;

use App\Models\StoreReturnRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreReturnRegistration>
 */
class StoreReturnRegistrationFactory extends Factory
{
    protected $model = StoreReturnRegistration::class;

    public function forRegistrationGroup(string $registrationGroupKey): static
    {
        return $this->state(fn (): array => [
            'registration_group_key' => $registrationGroupKey,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'returned_at' => fake()->date(),
            'institution_sk_code' => fake()->numerify('SK####'),
            'institution_name' => fake()->company(),
            'item_name' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 20),
            'status' => '정상',
            'freight' => '선불',
            'notes' => fake()->optional()->sentence(),
            'cs_team' => fake()->optional()->name(),
            'registered_by' => User::factory(),
        ];
    }
}
