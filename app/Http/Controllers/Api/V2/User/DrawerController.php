<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\UserFollow;
use App\Models\UserInterest;
use Exception;

/**
 * Drawer menu aggregate (§15): one round-trip for a menu that opens
 * constantly. Every number derives from existing tables.
 */
class DrawerController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();

            $member_group_ids = GroupMember::where("user_id", $user->id)->pluck("group_id");
            $groups = Group::whereIn("id", $member_group_ids)->get();

            $topics = UserInterest::with("category")
                ->where("user_id", $user->id)
                ->get()
                ->map(fn ($row) => ["id" => $row->category?->id, "name" => $row->category?->name])
                ->filter(fn ($t) => !empty($t["id"]))
                ->values()
                ->all();

            $serialize_group = fn ($group) => [
                "id" => $group->id,
                "name" => $group->name,
                "uuid" => $group->uuid,
                "image" => $group->image,
            ];

            return ApiHelper::validResponse("Drawer returned successfully", [
                "profile" => [
                    "name" => $user->full_name,
                    "username" => $user->username,
                    "avatar" => $user->avatar,
                    "is_verified" => !empty($user->therapist?->verified_at),
                ],
                "following_count" => UserFollow::where("follower_id", $user->id)->count(),
                "followers_count" => UserFollow::where("followed_id", $user->id)->count(),
                "topics" => $topics,
                "groups" => $groups->where("group_access", StatusConstants::OPENED)
                    ->map($serialize_group)->values()->all(),
                "private_groups" => $groups->where("group_access", StatusConstants::CLOSED)
                    ->map($serialize_group)->values()->all(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
