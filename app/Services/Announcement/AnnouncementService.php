<?php

namespace App\Services\Announcement;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Announcement;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Media\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    protected $file_service;

    public function __construct(FileService $file_service)
    {
        $this->file_service = $file_service;
    }

    public static function getById($key, $column = "id")
    {
        $announcement = Announcement::where($column, $key)->first();
        if (empty($announcement)) {
            throw new ModelNotFoundException("Announcement not found");
        }
        return $announcement;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => 'required|string|in:Group_Admins,General',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'status' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function send(array $data, $id = null)
    {
        // Validate the data
        $data = self::validate($data);
        $data['user_id'] = auth()->user()?->id;

        if (!empty($banner_image = $data["banner_image"] ?? null)) {
            $data["banner_image"] = $this->file_service->saveFromFileIntoStorage($banner_image, FileConstants::ANNOUNCEMENT_BANNER_PATH, null, auth()->id());
        }

        if ($id) {
            $announcement = self::getById($id);
            $old_announcement = $announcement->toArray();
            // Update the announcement with validated data
            $announcement->update($data);
            $announcement->refresh();  // Refresh the announcement instance
            // Log the activity
            (new ActivityLogService)
                ->setEvent("updated")
                ->setTitle("Announcement Updated")
                ->setDescription(auth()->user()?->full_name . " updated an announcement")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::ANNOUNCEMENT_UPDATED)
                ->setModel(Announcement::class, $announcement->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(
                    ["Old Announcement" => $old_announcement],
                    ["Announcement" => $announcement->toArray()]
                )
                ->setUrl(request()->fullUrl())
                ->log();
        } else {
            // Create new announcement
            $announcement = Announcement::create($data);
            $announcement->refresh();  // Refresh the announcement instance
            // Log the activity
            (new ActivityLogService)
                ->setEvent("created")
                ->setTitle("Announcement Created")
                ->setDescription(auth()->user()?->full_name . " created an announcement")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::ANNOUNCEMENT_CREATED)
                ->setModel(Announcement::class, $announcement->id)
                ->setAdmin(auth()->user()?->id)
                ->setData(
                    ["Announcement" => $announcement->toArray()]
                )
                ->setUrl(request()->fullUrl())
                ->log();
        }

        return $announcement;
    }

    public static function list(array $data = [])
    {
        $builder = Announcement::latest();

        if (!empty($status = $data["status"] ?? null)) {
            $builder = $builder->where("status", $status);
        }

        if (!empty($audience = $data["audience"] ?? null)) {
            $builder = $builder->where("audience", $audience);
        }

        return $builder;
    }

    public static function delete(string $id)
    {
        $announcement = self::getById($id);
        $old_announcement = $announcement->toArray();
        $announcement->delete();

        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Announcement Deleted")
            ->setDescription(auth()->user()?->full_name . " deleted an announcement")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::ANNOUNCEMENT_DELETED)
            ->setModel(Announcement::class, $announcement->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                ["Old Announcement" => $old_announcement],
            )
            ->setUrl(request()->fullUrl())
            ->log();
    }

    public function changeStatus(Request $request, $id)
    {
        $status = $request->input('status');
        if (!in_array($status, [StatusConstants::ACTIVE, StatusConstants::INACTIVE])) {
            throw new InvalidRequestException("Invalid status provided");
        }

        $announcement = $this->getById($id);
        $old_announcement = $announcement->toArray();
        $announcement->update([
            "status" => $status
        ]);
        $announcement->refresh();  // Refresh the announcement instance
        if ($announcement->status === StatusConstants::ACTIVE) {
            $announcement->update([
                'published_at' => now(),
            ]);
            $announcement->refresh();  // Refresh the announcement instance again if needed
        }
        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Announcement Status Changed")
            ->setDescription(auth()->user()?->full_name . " changed announcement status")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::ANNOUNCEMENT_UPDATED)
            ->setModel(Announcement::class, $announcement->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                ["Old Announcement" => $old_announcement],
                ["Announcement" => $announcement->toArray()]
            )
            ->setUrl(request()->fullUrl())
            ->log();
    }
}
