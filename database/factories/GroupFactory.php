<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'uuid' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'about' => fake()->paragraph(),
            'status' => StatusConstants::ACTIVE,
            'can_post' => 1,
            'group_access' => StatusConstants::OPENED,
            'created_by' => User::factory(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'group_access' => StatusConstants::CLOSED,
        ]);
    }
}
