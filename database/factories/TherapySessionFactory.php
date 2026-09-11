<?php

namespace Database\Factories;

use App\Constants\Therapist\TherapistConstants;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapySession>
 */
class TherapySessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'therapist_id' => Therapist::factory(),
            'starts_at' => now()->addDay()->setTime(10, 0),
            'duration_minutes' => 50,
            'format' => TherapistConstants::FORMAT_VIDEO,
            'status' => TherapistConstants::SESSION_CONFIRMED,
            'amount' => 15000,
            'currency' => 'NGN',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TherapistConstants::SESSION_COMPLETED,
            'starts_at' => now()->subDays(2),
            'ended_at' => now()->subDays(2)->addMinutes(50),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TherapistConstants::SESSION_PENDING_PAYMENT,
            'hold_expires_at' => now()->addMinutes(config('therapist.booking.hold_minutes')),
        ]);
    }
}
