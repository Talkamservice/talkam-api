<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MoodCheckin>
 */
class MoodCheckinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mood' => fake()->numberBetween(1, 5),
            'checked_in_on' => now()->toDateString(),
        ];
    }
}
