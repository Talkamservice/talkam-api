<?php

namespace Database\Factories;

use App\Constants\Auth\PinConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pin>
 */
class PinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => (string) fake()->unique()->numberBetween(100000, 999999),
            'type' => PinConstants::TYPE_VERIFY_EMAIL,
            'expires_at' => now()->addSeconds(config('system.configuration.pin_expiry')),
        ];
    }

    public function passwordReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PinConstants::TYPE_PASSWORD_RESET,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
