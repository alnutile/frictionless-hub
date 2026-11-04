<?php

namespace Database\Factories;

use App\Models\Initiative;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStage>
 */
class WorkflowStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'name' => fake()->unique()->word(),
            'position' => 0,
            'notes' => null,
            'ai_assisted' => false,
        ];
    }
}
