<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Avatar>
 */
class AvatarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'image' => fake()->imageUrl(),
            'description' => fake()->sentence(),
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
