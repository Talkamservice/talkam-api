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
    public $post_schedule_service;

    public function __construct()
    {
        $this->post_poll_service = new PostPollService;
        $this->post_schedule_service = new PostScheduleService;
        $this->post_attachment_service = new PostAttachmentService;
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
            "category_id" => "required|numeric|exists:post_categories,id",
            "type" => "required|string|" . Rule::in(PostConstants::TYPES),
            "title" => "nullable|string",
            "body" => "nullable|string",
            "status" => "nullable|string|" . Rule::in(StatusConstants::POST_STATUS_OPTIONS),
            "cover" => "string|nullable",
            "publish_at" => "nullable",
            "is_anonymous" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
            "can_comment" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
            "attachments" => "nullable|array|" . Rule::requiredIf($data["type"] == PostConstants::FILE),
            "attachments*.url" => "nullable|string|" . Rule::requiredIf($data["type"] == PostConstants::FILE),
            "attachments*.type" => "nullable|string|" . Rule::requiredIf($data["type"] == PostConstants::FILE),
            "poll" => "nullable|array|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            "poll.type" => "nullable|string|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            "poll.options" => "nullable|array|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            'poll.options.*' => "nullable|string|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
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
                "status" => $data["status"] ?? StatusConstants::ACTIVE,
                "type" => $data["type"]
            ], $data);

            $attachments = $data["attachments"] ?? null;
            $poll = $data["poll"] ?? null;
            unset($data["poll"], $data["attachments"]);

            $post = Post::create($data);

            if (isset($attachments)) {
                foreach ($attachments as $key => $attachment) {
                    $this->post_attachment_service->create([
                        'post_id' => $post->id,
                        'user_id' => $user->id,
                        ...$attachment
                    ]);
                }
            }

            if (isset($poll)) {
                $this->post_poll_service->create([
                    "post_id" => $post->id,
                    ...$poll
                ]);
            }

            if (!empty($post->publish_at) && !in_array($post->status, [StatusConstants::DRAFTED])) {
                $this->post_schedule_service->addToSchedule($post);
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

        $attachments = $data["attachments"] ?? null;
        $poll = $data["poll"] ?? null;
        unset($data["poll"], $data["attachments"]);
        $post->update($data);

        if (isset($attachments)) {
            $post->attachments()->delete();
            foreach ($attachments as $key => $attachment) {
                $this->post_attachment_service->create([
                    "post_id" => $post->id,
                    "user_id" => $post->user_id,
                    ...$attachment
                ]);
            }
        }


        if (isset($poll)) {
            $post->polls()->delete();
            $this->post_poll_service->create([
                "post_id" => $post->id,
                ...$poll
            ]);
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
