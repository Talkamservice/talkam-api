<?php

namespace Database\Factories;

use App\Constants\Therapist\TherapistConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistApplication>
 */
class TherapistApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => TherapistConstants::STATUS_DRAFT,
            'credential_type' => 'Clinical Psychologist',
            'years_experience' => fake()->numberBetween(1, 20),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TherapistConstants::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'bio' => fake()->paragraph(),
            'session_duration' => config('therapist.session_durations')[0],
            'buffer_minutes' => config('therapist.buffers')[0],
            'session_rate' => config('therapist.session_rate.min'),
        ]);
    }
}
