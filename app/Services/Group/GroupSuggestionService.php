<?php

namespace App\Services\Group;

use App\Constants\General\StatusConstants;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\UserInterest;

class GroupSuggestionService
{
    /**
     * Organic suggestions: open groups matching the user's interest
     * categories first, then by member count, excluding joined groups.
     * Paid promotions have their own surface and never rank here.
     */
    public static function suggested(User $user)
    {
        $interest_ids = UserInterest::where('user_id', $user->id)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $joined_ids = GroupMember::where('user_id', $user->id)->pluck('group_id');

        $builder = Group::status()
            ->where('group_access', StatusConstants::OPENED)
            ->whereNotIn('id', $joined_ids)
            ->withCount('members');

        if (!empty($interest_ids)) {
            $ids = implode(',', $interest_ids);
            $builder = $builder
                ->orderByRaw("CASE WHEN category_id IN ($ids) THEN 0 ELSE 1 END")
                ->orderByDesc('members_count');
        } else {
            $builder = $builder->orderByDesc('members_count');
        }

        return $builder;
    }
}
