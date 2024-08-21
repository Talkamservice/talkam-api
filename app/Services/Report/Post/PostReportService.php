<?php

namespace App\Services\Report\Post;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostReport;
use App\Notifications\User\PostsRemovedFromApplicationNotification;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostReportService
{
    protected $user_service;
    public function __construct()
    {
        $this->user_service = new UserService;
    }

    public static function getById($id): PostReport
    {
        $report = PostReport::find($id);
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
                throw new InvalidRequestException("You cannot make changes to a resolved report");
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

    public static function delete($reported_post_id)
    {
        $reported_post = self::getById($reported_post_id);
        $reported_post->post->delete();
        Notification::send( $reported_post, new PostsRemovedFromApplicationNotification($reported_post));
        // return  $reported_post->refresh();
    }
}
