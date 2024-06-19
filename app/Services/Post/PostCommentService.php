<?php

namespace App\Services\Post;

use App\Constants\Post\PostConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostComment;
use App\Models\UserCommentReaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostCommentService
{
    public static function getById($id): PostComment
    {
        $post_attachment = PostComment::find($id);
        if (empty($post_attachment)) {
            throw new ModelNotFoundException("Post comment not found");
        }
        return $post_attachment;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "comment" => "required|string",
            "attachment" => "nullable|string",
            "post_id" => "required|exists:posts,id",
            "parent_id" => "nullable|exists:post_comments,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["user_id"] = auth()->id();
        $post = PostComment::create($data);
        return $post;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);
        return $post->refresh();
    }

    public static function delete($post_comment_id)
    {
        $post = self::getById($post_comment_id);
        $post->delete();
    }

    public static function list($post_id, $comment_id = null)
    {
        $builder = PostComment::where("post_id", $post_id)->latest();
        if (!empty($comment_id)) {
            $builder = $builder->where("parent_id", $comment_id);
        }
        return $builder;
    }

    public static function handleReaction(array $data)
    {
        $validator = Validator::make($data, [
            "comment_id" => "required|numeric|exists:post_comments,id",
            "action" => "required|string|" . Rule::in(PostConstants::REACTIONS),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $user = auth()->user();
        $comment_id = $data["comment_id"];
        $action = $data["action"];

        if (self::isReactionPresent($comment_id, $user->id, $action)) {
            self::removeReaction($comment_id, $user->id, $action);
        } else {
            self::addReaction($comment_id, $user->id, $action);
        }

        return [
            "action" => $action,
            "status" => self::isReactionPresent($comment_id, $user->id, $action),
        ];
    }

    public static function isReactionPresent($comment_id, $user_id, $action = PostConstants::LIKE)
    {
        return UserCommentReaction::where([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $action,
        ])->exists();
    }

    public static function removeReaction($comment_id, $user_id, $action = PostConstants::LIKE)
    {
        UserCommentReaction::where([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $action,
        ])->delete();
    }

    public static function addReaction($comment_id, $user_id, $action = PostConstants::LIKE)
    {
        $inverse = ($action == PostConstants::DISLIKE) ? PostConstants::LIKE : PostConstants::DISLIKE;

        UserCommentReaction::create([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $action,
        ]);

        //Remove Inverse
        UserCommentReaction::where([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $inverse,
        ])->delete();
    }
}
