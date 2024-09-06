<?php

namespace App\Services\BulkMessages;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Jobs\SendUserNotificationJob;
use App\Models\SendBulkNotification;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    public static function getById($key, $column = "id")
    {
        $notification = SendBulkNotification::where($column, $key)->first();
        if (empty($notification)) {
            throw new ModelNotFoundException("Notification not found");
        }
        return $notification;
    }

    public static function validate(array $data, $id = null)
    {
        if ($data['type'] === 'Broadcast') {
            $data['user_id'] = User::pluck('id')->toArray();
        }

        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:Single,Broadcast',
            'status' => 'nullable|string',
            'schedule_date' => 'nullable|date',
            'user_id' => [
                'required_if:type,Single',
                'exists:users,id',
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function send(array $data, $notificationId = null)
    {
        $data = self::validate($data);

        if ($notificationId) {
            $notification = self::getById($notificationId);
            $old_notification = $notification->toArray();

            $notification->update($data);

            (new ActivityLogService)
                ->setEvent("updated")
                ->setTitle("Bulk Notification Updated")
                ->setDescription(auth()->user()?->full_name . " updated a bulk notification")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::UPDATED_USER_NOTIFICATION)
                ->setModel(SendBulkNotification::class, $notification->id)
                ->setAdmin(auth()->user()->id)
                ->setData([
                    "Old Bulk Notification" => $old_notification,
                    "Updated Bulk Notification" => $notification->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
        } else {
            $notification = SendBulkNotification::create($data);
            (new ActivityLogService)
                ->setEvent("created")
                ->setTitle("Bulk Notification Created")
                ->setDescription(auth()->user()?->full_name . " created a bulk notification")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::CREATED_USER_NOTIFICATION)
                ->setModel(SendBulkNotification::class, $notification->id)
                ->setAdmin(auth()->user()->id)
                ->setData([
                    "Bulk Notification" => $notification->refresh()->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
        }

        // Determine recipients based on type
        if ($data['type'] === 'Single') {
            $userIds = is_array($data['user_id']) ? $data['user_id'] : [$data['user_id']];
            $notification->recipients()->sync($userIds);
        } elseif ($data['type'] === 'Broadcast') {
            $userIds = User::where('status', StatusConstants::ACTIVE)->pluck('id')->toArray();
            $notification->recipients()->sync($userIds);
        }

        $sendAt = isset($data['schedule_date']) ? Carbon::parse($data['schedule_date']) : null;
        if ($sendAt?->isPast() || is_null($sendAt)) {
            MethodsHelper::dispatchJob(new SendUserNotificationJob($notification));
            $notification->update([
                "status" => StatusConstants::SENT,
            ]);

            (new ActivityLogService)
                ->setEvent("sent")
                ->setTitle("Bulk Notification Sent")
                ->setDescription(auth()->user()?->full_name . " sent a bulk notification")
                ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
                ->setActivity(ActivitiesConstants::SENT_USER_NOTIFICATION)
                ->setModel(SendBulkNotification::class, $notification->id)
                ->setAdmin(auth()->user()->id)
                ->setData([
                    "Bulk Notification" => $notification->toArray(),
                ])
                ->setUrl(request()->fullUrl())
                ->log();
        }

        return $notification;
    }

    public static function list(array $data = [])
    {
        $builder = SendBulkNotification::latest();

        if (!empty($type = $data["type"] ?? null)) {
            $builder->where("type", $type);
        }

        if (!empty($status = $data["status"] ?? null)) {
            $builder->where("status", $status);
        }

        if (!empty($recipients = $data["recipients"] ?? null)) {
            $builder->whereHas('recipients', function ($query) use ($recipients) {
                $query->whereIn('user_id', $recipients);
            });
        }

        return $builder;
    }

    public function delete(string $id)
    {
        $notification = self::getById($id);
        $old_notification_data = $notification->toArray();

        $notification->recipients()->detach();
        $notification->delete();

        (new ActivityLogService)
            ->setEvent("deleted")
            ->setTitle("Bulk Notification Deleted")
            ->setDescription(auth()->user()?->full_name . " deleted a bulk notification")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::DELETED_USER_NOTIFICATION)
            ->setModel(SendBulkNotification::class, $notification->id)
            ->setAdmin(auth()->user()->id)
            ->setData([
                "Bulk Notification" => $old_notification_data,
            ])
            ->setUrl(request()->fullUrl())
            ->log();

        return $notification;
    }

    public function changeStatus(Request $request, $id)
    {
        $status = $request->input('status');
        if (!in_array($status, [StatusConstants::SENT, StatusConstants::PENDING])) {
            throw new InvalidRequestException("Invalid status provided");
        }

        $notification = self::getById($id);
        $notification->update([
            "status" => $status
        ]);
    }
}
