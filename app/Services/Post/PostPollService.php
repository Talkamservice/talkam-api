<?php

namespace App\Services\Post;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostPoll;
use App\Models\UserPollChoice;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostPollService
{
    public static function getById($id): PostPoll
    {
        $post_poll = PostPoll::find($id);
        if (empty($post_poll)) {
            throw new ModelNotFoundException("Post poll not found");
        }
        return $post_poll;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "type" => "required|string|in:Image,Text",
            "options" => "required|array",
            "options.*" => "required|string",
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
        $options = $data["options"];
        unset($data["options"]);

        foreach ($options ?? [] as $key => $option) {
            $data["option"] = $option;
            $poll = PostPoll::create($data);
        }

        return $poll;
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
        $posts = PostPoll::where("post_id", $post_id)->latest();
        return $posts;
    }

    public static function select(array $data)
    {
        $validator = Validator::make($data, [
            
            "poll_id" => "required|numeric|exists:post_polls,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $poll = self::getById($data["poll_id"]);

        UserPollChoice::updateOrCreate([
            "post_id" => $poll->post_id,
            "user_id" => auth()->id(),
        ], [
            "poll_id" => $poll->id,
        ]);

        return $poll;
    }
}
