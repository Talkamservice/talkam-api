<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistAvailability>
 */
class TherapistAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'active' => true,
        ];
    }
}
