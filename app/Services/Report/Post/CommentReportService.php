<?php

namespace App\Services\Report\Post;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\CommentReport;
use App\Notifications\User\CommentsRemovedFromApplicationNotification;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommentReportService
{
    protected $user_service;
    public function __construct()
    {
        $this->user_service = new UserService;
    }

    public static function getById($id): CommentReport
    {
        $report = CommentReport::find($id);
        if (empty($report)) {
            throw new ModelNotFoundException("Report not found");
        }
        return $report;
    }

    public static function validate(array $data)
    {
        $validator = Validator::make($data, [
            "action" => "required|string|in:Suspended,Activated,Resolved"
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function changeStatus(array $data, $report_id)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $report = self::getById($report_id);

            if (in_array($report->status, [StatusConstants::RESOLVED])) {
                throw new InvalidRequestException("You cannot make changes when you resolved a report");
            }

            $report->update([
                'status' => StatusConstants::RESOLVED
            ]);
// Log the activity
(new ActivityLogService)
->setEvent("resolved")
->setTitle("Resolved Reported Comment")
->setDescription(auth()->user()?->full_name . "resolved a reported comment")
->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
->setActivity(ActivitiesConstants::RESOLVED_REPORTED_COMMENT)
->setModel(CommentReport::class, $report->id)
->setAdmin(auth()->user()?->id)
->setData(["Report comment" => $report->refresh()->toArray()])
->setUrl(request()->fullUrl())
->log();
            DB::commit();
            return $report->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function delete($reported_comment_id)
    {
        $reported_comment = self::getById($reported_comment_id);
        $reported_comment->comment->delete();
        Notification::send($reported_comment->comment->user, new CommentsRemovedFromApplicationNotification($reported_comment));

        // Log the activity
        (new ActivityLogService)
        ->setEvent("deleted")
        ->setTitle("Deleted Reported Comment")
        ->setDescription(auth()->user()?->full_name . " deleted a reported comment")
        ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
        ->setActivity(ActivitiesConstants::DELETED_REPORTED_COMMENT)
        ->setModel(CommentReport::class, $reported_comment->id)
        ->setAdmin(auth()->user()?->id)
        ->setData(["Reported comment" => $reported_comment->refresh()->toArray()])
        ->setUrl(request()->fullUrl())
        ->log();
        // return $reported_comment->refresh();
    }
}
