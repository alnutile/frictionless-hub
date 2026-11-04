<?php

namespace Database\Factories;

use App\Models\Dimension;
use App\Models\Initiative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dimension>
 */
class DimensionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'position' => 0,
        ];
    }
}
