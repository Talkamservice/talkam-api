<?php

namespace Database\Factories;

use App\Constants\Therapist\SessionConstants;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SessionReschedule>
 */
class SessionRescheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => TherapySession::factory(),
            'requested_by' => User::factory(),
            'old_starts_at' => now()->addDay()->setTime(10, 0),
            'new_starts_at' => now()->addDays(2)->setTime(10, 0),
            'reason' => SessionConstants::REASON_PERSONAL_EMERGENCY,
            'status' => SessionConstants::RESCHEDULE_PENDING,
        ];
    }
}
