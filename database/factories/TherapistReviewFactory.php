<?php

namespace Database\Factories;

use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TherapistReview>
 */
class TherapistReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => TherapySession::factory()->completed(),
            'user_id' => User::factory(),
            'therapist_id' => function (array $attributes) {
                return TherapySession::find($attributes['session_id'])->therapist_id;
            },
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
