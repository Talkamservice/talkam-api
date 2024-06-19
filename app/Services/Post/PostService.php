<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\Post;
use App\Services\Post\PostAttachmentService;
use App\Services\Post\PostPollService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostService
{
    public $post_attachment_service;
    public $post_poll_service;

    public function __construct()
    {
        $this->post_attachment_service = new PostAttachmentService;
        $this->post_poll_service = new PostPollService;
    }

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
            "category_id" => "required|string|exists:post_categories,id",
            "type" => "required|string|" . Rule::in(PostConstants::TYPES),
            "title" => "required|string",
            "body" => "nullable|string",
            "status" => "required|string|" . Rule::in(StatusConstants::ACTIVE_OPTIONS),
            "cover" => "string|nullable",
            "publish_at" => "nullable",
            "is_anonymous" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
            "can_comment" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
            "attachments" => "nullable|array",
            "attachments*.url" => "required|string",
            "attachments*.type" => "required|string",
            "poll" => "nullable|array",
            "poll.type" => "required|string",
            "poll.options" => "required|array",
            'poll.options.*' => 'required|string',
        ], [
            "cover.required" => "The cover image url is required",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $user = auth()->user();

            $data = array_merge([
                "uuid" => self::getUuid(),
                "user_id" => $user->id,
                "status" => StatusConstants::ACTIVE,
                "type" => $data["type"]
            ], $data);

            if (isset($data["attachments"])) {
                $attachments = $data["attachments"];
                unset($data["attachments"]);
            }

            if (isset($data["poll"])) {
                $poll = $data["poll"];
                unset($data["poll"]);
            }

            $post = Post::create($data);

            if (isset($attachments)) {
                foreach ($attachments ?? [] as $key => $attachment) {
                    $this->post_attachment_service->create(array_merge([
                        "post_id" => $post->id,
                        "user_id" => $user->id,
                    ], $attachment));
                }
            }

            if (isset($poll)) {
                $this->post_poll_service->create(array_merge([
                    "post_id" => $post->id,
                ], $poll));
            }

            DB::commit();
            return $post;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);

        if (isset($data["attachments"])) {
            $this->post_attachment_service->create(array_merge([
                "post_id" => $post->id,
                "user_id" => $post->user_id,
            ], $data["attachments"]));
        }

        return $post->refresh();
    }

    public static function delete($post_id)
    {
        $post = self::getById($post_id);
        $post->delete();
    }

    public static function getUuid()
    {
        $code = strtoupper(MethodsHelper::getRandomToken(6));
        if (Post::where("uuid", $code)->count() > 0) {
            return self::getUuid();
        }
        return $code;
    }

    public static function list()
    {
        $posts = Post::latest();
        return $posts;
    }
}
