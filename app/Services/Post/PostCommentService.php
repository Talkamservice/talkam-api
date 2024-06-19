<?php

namespace App\Services\Post;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostComment;
use Illuminate\Support\Facades\Validator;
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
}
