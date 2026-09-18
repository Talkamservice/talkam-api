<?php

namespace Database\Factories;

use App\Constants\Account\User\ConsentConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserConsent>
 */
class UserConsentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => ConsentConstants::ACCOUNT_OPERATION,
            'granted' => true,
            'granted_at' => now(),
            'revoked_at' => null,
            'policy_version' => 'v1.0',
        ];
    }

    public function key(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }
}
