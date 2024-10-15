<?php

namespace App\Services\Feedback;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Mail\ResponseToFeedbackMail;
use App\Models\Feedback;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $feedback = Feedback::find($id);

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
            "platform" => 'nullable|string',
            "attachments" => "nullable|array",
            "feedback_type" => "required|string",
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
                "platform" => $data["platform"] ?? null,
                "feedback_type" => $data["feedback_type"],
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

    public function respond(Request $request, $feedback_id)
    {
        DB::beginTransaction();
        try {
            // Fetch the feedback and get user details (assuming feedback contains user info)
            $feedback = self::getById($feedback_id);

            $feedback = self::getById($feedback_id);
            $message = $request->input('message');
            $email = $feedback->email; // Email from feedback data
            $recipientName = $feedback->name; // Assuming you have the recipient's name in feedback

            // Send the email directly
            Mail::to($email)->send(new ResponseToFeedbackMail($message, $recipientName));

            DB::commit();
            return $feedback->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function delete($feedback_id)
    {
        $feedback = self::getById($feedback_id);
        $feedback->delete();
    }

    public function changeStatus(array $data, $report_id)
    {
        DB::beginTransaction();
        try {
            $feedback = self::getById($report_id);

            if ($feedback->status === StatusConstants::RESOLVED) {
                throw new InvalidRequestException("You cannot make changes when you resolved a report");
            }

            $feedback->update([
                'status' => StatusConstants::RESOLVED
            ]);
            // Log the activity
            (new ActivityLogService)
                ->setEvent("resolved")
                ->setTitle("Resolved Feedback")
                ->setDescription(auth()->user()?->full_name . "resolved a feedback")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::RESOLVED_FEEDBACK)
                ->setModel(Feedback::class, $feedback->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(["Feedback" => $feedback->refresh()->toArray()])
                ->setUrl(request()->fullUrl())
                ->log();
            DB::commit();
            return $feedback->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function list(array $data = [])
    {
        $query = Feedback::query();

        // Apply search filters
        if (!empty($key = $data["search"] ?? null)) {
            $query->where(function ($query) use ($key) {
                $query->where("feedback_type", "LIKE", "%$key%")
                ->orWhere("platform", "LIKE", "%$key%")
                    ->orWhere("status", "LIKE", "%$key%");
            });
        }

        return $query;
    }
}
