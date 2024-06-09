<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Post;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostService
{
    public static function getById($id): Post
    {
        $post = Post::find($id);
        if (empty($post)) {
            throw new ModelNotFoundException("Post not found");
        }
        return $post;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "name" => "bail|required|string",
            "description" => "bail|nullable|string",
            "status" => "bail|required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "image" => "bail|nullable|string|" . Rule::requiredIf(empty($id)),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $post =  Post::create($data);
        return $post;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);
        return $post->refresh();
    }

    public static function delete($post_id)
    {
        $post = self::getById($post_id);
        $post->delete();
    }


    public static function list()
    {
        $posts = Post::latest();
        return $posts;
    }
}
