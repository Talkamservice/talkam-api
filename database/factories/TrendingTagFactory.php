<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrendingTag>
 */
class TrendingTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tag' => fake()->unique()->word(),
            'count' => fake()->numberBetween(1, 100),
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
