<?php

namespace App\Services\Post;

use App\Constants\Post\PostConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\BlockedUser;
use App\Models\CommentReport;
use App\Models\PostReport;
use App\Models\UserPostReaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostReactionService
{
    public static function getById($id): UserPostReaction
    {
        $post_reaction = UserPostReaction::find($id);
        if (empty($post_reaction)) {
            throw new ModelNotFoundException("Post reaction not found");
        }
        return $post_reaction;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "post_id" => "required|numeric|exists:posts,id",
            "action" => "required|string|" . Rule::in(PostConstants::REACTIONS),
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
        $post_id = $data["post_id"];
        $action = $data["action"];

        $reaction = self::toggleReaction($post_id, $user->id, $action);

        return [
            "action" => $action,
            "status" => $reaction,
        ];
    }

    private static function toggleReaction($post_id, $user_id, $action)
    {
        if (self::isReactionPresent($post_id, $user_id, $action)) {
            self::removeReaction($post_id, $user_id, $action);
        } else {
            self::addReaction($post_id, $user_id, $action);
        }

        return self::isReactionPresent($post_id, $user_id, $action);
    }

    public static function isReactionPresent($post_id, $user_id, $action = PostConstants::LIKE)
    {
        return UserPostReaction::where([
            "post_id" => $post_id,
            "user_id" => $user_id,
            "action" => $action,
        ])->exists();
    }

    public static function removeReaction($post_id, $user_id, $action = PostConstants::LIKE)
    {
        UserPostReaction::where([
            "post_id" => $post_id,
            "user_id" => $user_id,
            "action" => $action,
        ])->delete();
    }

    public static function addReaction($post_id, $user_id, $action = PostConstants::LIKE)
    {
        $inverse = ($action == PostConstants::DISLIKE) ? PostConstants::LIKE : PostConstants::DISLIKE;
        UserPostReaction::create([
            "post_id" => $post_id,
            "user_id" => $user_id,
            "action" => $action,
        ]);

        //Remove Inverse
        UserPostReaction::where([
            "post_id" => $post_id,
            "user_id" => $user_id,
            "action" => $inverse,
        ])->delete();
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);
        return $post->refresh();
    }

    public static function delete($poll_id)
    {
        $post = self::getById($poll_id);
        $post->delete();
    }

    public static function list($post_id)
    {
        $posts = UserPostReaction::where("post_id", $post_id)->latest();
        return $posts;
    }

    public static function report($data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "post_id" => "required|numeric|exists:posts,id",
                "reason" => "required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $data["user_id"] = auth()->id();
            $report = PostReport::create($data);

            DB::commit();
            return $report;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function block($data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "post_id" => "nullable|numeric|exists:posts,id",
                "blocked_user_id" => "required|numeric|exists:users,id",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();
            $data["blocker_id"] = auth()->id();

            $blocked_user = BlockedUser::firstOrCreate([
                "blocker_id" => $data["blocker_id"],
                "blocked_user_id" => $data["blocked_user_id"]
            ]);

            DB::commit();
            return $blocked_user;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function reportComent($data)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($data, [
                "comment_id" => "required|numeric|exists:post_comments,id",
                "post_id" => "required|numeric|exists:posts,id",
                "reason" => "required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $data["user_id"] = auth()->id();
            $report = CommentReport::create($data);

            DB::commit();
            return $report;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
