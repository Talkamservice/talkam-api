<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_status' => 1,
            'is_anonymous' => 0,
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
