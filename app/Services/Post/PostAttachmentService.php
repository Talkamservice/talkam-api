<?php

namespace App\Services\Post;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostAttachment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostAttachmentService
{
    public static function getById($id): PostAttachment
    {
        $post_attachment = PostAttachment::find($id);
        if (empty($post_attachment)) {
            throw new ModelNotFoundException("Post attachment not found");
        }
        return $post_attachment;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "type" => "required|string|in:Image,Video,Others",
            "url" => "required|string",
            "user_id" => "required|numeric|exists:users,id",
            "post_id" => "required|numeric|exists:posts,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $post = PostAttachment::create($data);
        return $post;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);
        return $post->refresh();
    }

    public static function delete($post_attachment_id)
    {
        $post = self::getById($post_attachment_id);
        $post->delete();
    }

    public static function list($post_id)
    {
        $posts = PostAttachment::where("post_id", $post_id)->latest();
        return $posts;
    }
}
