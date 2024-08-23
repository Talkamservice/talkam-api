<?php

namespace App\Services\Report\Post;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\CommentReport;
use App\Notifications\User\CommentsRemovedFromApplicationNotification;
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
        Notification::send($reported_comment->user, new CommentsRemovedFromApplicationNotification($reported_comment));
        // return $reported_comment->refresh();
    }
}
