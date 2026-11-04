<?php

namespace Database\Factories;

use App\Models\StageTool;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StageTool>
 */
class StageToolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_stage_id' => WorkflowStage::factory(),
            'name' => fake()->word(),
            'is_ai' => false,
            'notes' => null,
        ];
    }
}
