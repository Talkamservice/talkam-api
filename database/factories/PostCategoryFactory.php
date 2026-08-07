<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostCategory>
 */
class PostCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'status' => StatusConstants::ACTIVE,
        ];
    }

    public function interestTopic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PostCategoryConstants::TYPE_INTEREST_TOPIC,
        ]);
    }
}
