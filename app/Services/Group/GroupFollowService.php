<?php

namespace App\Services\Group;

use App\Models\Group;
use App\Models\GroupFollow;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GroupFollowService
{
    /**
     * Lighter "Follow Group" (overflow menu): updates without membership.
     * Returns whether the caller now follows the group.
     */
    public function toggle(User $user, array $data): bool
    {
        $validator = Validator::make($data, [
            'group_id' => 'required|numeric|exists:groups,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $group_id = (int) $validator->validated()['group_id'];

        $existing = GroupFollow::where([
            'user_id' => $user->id,
            'group_id' => $group_id,
        ])->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        GroupFollow::firstOrCreate([
            'user_id' => $user->id,
            'group_id' => $group_id,
        ]);

        return true;
    }

    public static function followedGroups(User $user)
    {
        return Group::whereIn('id', GroupFollow::where('user_id', $user->id)->pluck('group_id'));
    }
}
