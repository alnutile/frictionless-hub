<?php

namespace Database\Factories;

use App\Enums\Frequency;
use App\Models\FrictionPoint;
use App\Models\Initiative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrictionPoint>
 */
class FrictionPointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'workflow_stage_id' => null,
            'title' => fake()->sentence(4),
            'description' => null,
            'frequency' => fake()->randomElement(Frequency::cases()),
            'pain' => fake()->numberBetween(1, 5),
            'affected' => null,
        ];
    }
}
