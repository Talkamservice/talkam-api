<?php

namespace App\Services\Feedback;

use App\Constants\Media\FileConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\FeedbackAttachment;
use App\Services\Media\FileService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FeedbackAttachmentService
{
    public $file_service;

    public function __construct()
    {
        $this->file_service = new FileService;
    }
   
    public static function getById($id): FeedbackAttachment
    {
        $post_attachment = FeedbackAttachment::find($id);
        if (empty($post_attachment)) {
            throw new ModelNotFoundException("Feedback attachment not found");
        }
        return $post_attachment;
    }

    public function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "file" => "required|file",
            "feedback_id" => "required|numeric|exists:feedback,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        $data = self::validate($data);

        if (!empty($file = $data["file"] ?? null)) {
            $data["file"] = $this->file_service->saveFromFileIntoStorage($file, FileConstants::CATEGORY_PATH, null, auth()->id());
        }

        $post = FeedbackAttachment::create($data);
        return $post;
    }

    public function update(array $data, $id)
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
        $posts = FeedbackAttachment::where("post_id", $post_id)->latest();
        return $posts;
    }
}
