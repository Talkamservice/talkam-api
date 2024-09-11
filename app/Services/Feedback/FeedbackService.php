<?php

namespace App\Services\Feedback;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Feedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FeedbackService
{
    public $feedback_attachment_service;

    public function __construct()
    {
        $this->feedback_attachment_service = new FeedbackAttachmentService;
    }

    public static function getById($id)
    {
        $feedback = Feedback::where("id", $id)->first();

        if (empty($feedback)) {
            throw new ModelNotFoundException("Feedback not found");
        }

        return $feedback;
    }

    public static function validate(array $data)
    {
        $validator = Validator::make($data, [
            "name" => 'required|string',
            "email" => 'required|string|email',
            "content" => 'required|string',
            "platform" => 'required|string',
            "attachments" => "nullable|array",
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

            $feedback = Feedback::create([
                "name" => $data["name"],
                "email" => $data["email"],
                "content" => $data["content"],
                "platform" => $data["platform"],
            ]);

            $attachments = $data["attachments"] ?? null;

            if (!empty($attachments)) {
                $feedback->attachments()->delete();
                foreach ($attachments as $key => $attachment) {
                    $this->feedback_attachment_service->create([
                        'feedback_id' => $feedback->id,
                        "file" => $attachment
                    ]);
                }
            }

            DB::commit();
            return $feedback;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $feedback = self::getById($id);

            $feedback->update($data);

            $attachments = $data["attachments"] ?? null;
            if (!empty($attachments)) {
                $feedback->attachments()->delete();
                foreach ($attachments as $key => $attachment) {
                    $this->feedback_attachment_service->create([
                        'feedback_id' => $feedback->id,
                        "file" => $attachment
                    ]);
                }
            }


            DB::commit();
            return $feedback->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function delete($feedback_service_id)
    {
        $feedback_service = self::getById($feedback_service_id);
        $feedback_service->delete();
    }

    
}
