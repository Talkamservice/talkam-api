<?php

namespace App\Services\Report\Post;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Events\RefreshNotification;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostReport;
use App\Notifications\User\PostsRemovedFromApplicationNotification;
use App\Services\ActivityLog\ActivityLogService;
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
            // Log the activity
            (new ActivityLogService)
                ->setEvent("resolved")
                ->setTitle("Resolved Reported Post")
                ->setDescription(auth()->user()?->full_name . " resolved a reported post")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::RESOLVED_REPORTED_POST)
                ->setModel(PostReport::class, $report->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(["Group" => $report->refresh()->toArray()])
                ->setUrl(request()->fullUrl())
                ->log();
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
        Notification::send($reported_post->post->user, new PostsRemovedFromApplicationNotification($reported_post));
        broadcast(new RefreshNotification($reported_post->post->user_id));

        // Log the activity
        (new ActivityLogService)
            ->setEvent("deleted")
            ->setTitle("Deleted Reported Post")
            ->setDescription(auth()->user()?->full_name . " deleted a reported post")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::DELETED_REPORTED_POST)
            ->setModel(PostReport::class, $reported_post->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(["Reported Post" => $reported_post->refresh()->toArray()])
            ->setUrl(request()->fullUrl())
            ->log();
        // return  $reported_post->refresh();
    }

    public static function list(array $data = [])
    {
        $post_reports = PostReport::with(["user"]);

        if (!empty($key = $data["search"] ?? null)) {
            $post_reports = $post_reports->where("name", "LIKE", "%$key%");
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $post_reports = $post_reports->where("category_id", $key);
        } else {
            $post_reports = $post_reports->whereNull("category_id");
        }

        if (!empty($key = $data["sort"] ?? null)) {
            if ($key == "popular") {
                $post_reports = $post_reports->withCount('posts')->orderBy('posts_count', 'desc');
            }
        }

        return $post_reports;
    }
}
