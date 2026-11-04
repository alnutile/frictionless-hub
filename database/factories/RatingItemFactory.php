<?php

namespace Database\Factories;

use App\Models\RatingGroup;
use App\Models\RatingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingItem>
 */
class RatingItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating_group_id' => RatingGroup::factory(),
            'label' => fake()->words(3, true),
            'position' => 0,
        ];
    }
}
