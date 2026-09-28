<?php

namespace Database\Factories;

use App\Constants\General\StatusConstants;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationMember>
 */
class ConversationMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
