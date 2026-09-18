<?php

namespace Database\Factories;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupMember>
 */
class GroupMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'role' => UserConstants::MEMBER,
            'status' => StatusConstants::ACTIVE,
        ];
    }
}
