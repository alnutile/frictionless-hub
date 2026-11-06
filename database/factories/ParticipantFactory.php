<?php

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Initiative;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'status' => ParticipantStatus::Candidate,
        ];
    }
}
