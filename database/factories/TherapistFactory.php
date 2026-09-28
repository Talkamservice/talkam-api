<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Therapist>
 */
class TherapistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'credential_type' => 'Clinical Psychologist',
            'session_rate' => 15000,
            'session_formats' => ['video', 'voice'],
            'session_duration' => 50,
            'buffer_minutes' => 10,
            'years_experience' => fake()->numberBetween(2, 20),
            'verified_at' => now(),
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
