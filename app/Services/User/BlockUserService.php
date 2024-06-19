<?php

namespace App\Services\User;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\BlockedUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BlockUserService
{
    public static function getById($id): BlockedUser
    {
        $post_reaction = BlockedUser::find($id);
        if (empty($post_reaction)) {
            throw new ModelNotFoundException("Blocked user not found");
        }
        return $post_reaction;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "post_id" => "nullable|numeric|exists:posts,id",
            "blocked_user_id" => "required|numeric|exists:users,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);

        $user = auth()->user();
        $blocked_user_id = $data["blocked_user_id"];

        if (self::isBlocked($user->id, $blocked_user_id)) {
            self::removeBlock($user->id, $blocked_user_id);
        } else {
            self::addBlock($user->id, $blocked_user_id);
        }

        return self::isBlocked($user->id, $blocked_user_id);
    }

    public static function isBlocked($user_id, $blocked_user_id)
    {
        return BlockedUser::where([
            "blocker_id" => $user_id,
            "blocked_user_id" => $blocked_user_id,
        ])->exists();
    }

    public static function removeBlock($user_id, $blocked_user_id)
    {
        BlockedUser::where([
            "blocker_id" => $user_id,
            "blocked_user_id" => $blocked_user_id,
        ])->delete();
    }

    public static function addBlock($user_id, $blocked_user_id)
    {
        BlockedUser::create([
            "blocker_id" => $user_id,
            "blocked_user_id" => $blocked_user_id,
        ]);
    }

    public static function delete($poll_id)
    {
        $post = self::getById($poll_id);
        $post->delete();
    }

    public static function list($user_id)
    {
        $posts = BlockedUser::where("blocker_id", $user_id)->latest();
        return $posts;
    }
}
