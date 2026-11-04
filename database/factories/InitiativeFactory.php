<?php

namespace Database\Factories;

use App\Enums\Phase;
use App\Models\Initiative;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Initiative>
 */
class InitiativeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->facilitator(),
            'name' => fake()->words(3, true),
            'scope' => fake()->randomElement(['Company-wide', 'Platform org', 'Mobile team']),
            'description' => fake()->sentence(),
            'phase' => Phase::Discover,
        ];
    }
}
