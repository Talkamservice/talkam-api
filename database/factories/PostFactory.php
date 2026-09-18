<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'type' => PostConstants::TEXT,
            'uuid' => strtoupper(Str::random(6)),
            'user_id' => User::factory(),
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
