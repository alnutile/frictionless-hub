<?php

namespace Database\Factories;

use App\Models\Initiative;
use App\Models\StoryMapItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryMapItem>
 */
class StoryMapItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'parent_id' => null,
            'level' => StoryMapItem::LEVEL_ACTIVITY,
            'kind' => null,
            'title' => fake()->words(3, true),
            'position' => 0,
        ];
    }
}
