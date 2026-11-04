<?php

namespace Database\Factories;

use App\Models\Initiative;
use App\Models\RatingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingGroup>
 */
class RatingGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'name' => fake()->words(2, true),
            'position' => 0,
        ];
    }
}
