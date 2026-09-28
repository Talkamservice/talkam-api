<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'status' => StatusConstants::ACTIVE,
            'published_at' => now(),
        ];
    }
}
