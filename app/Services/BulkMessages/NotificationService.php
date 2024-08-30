<?php

namespace App\Services\bulkMessages;

use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Jobs\SendUserNotificationJob;
use App\Models\SendBulkNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
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
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:Single,Broadcast',
            'status' => 'nullable|string',
            'schedule_date' => 'nullable|date',
            'user_id' => [
                'required_if:type,Single,Broadcast', // required for 'single' and 'multiple'
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
        $data = $this->validate($data);
        // Handle existing or new notification
        if ($notificationId) {
            $notification = self::getById($notificationId);
            $notification->update($data);
        } else {
            $notification = SendBulkNotification::create($data);
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
                "status" => StatusConstants::SENT
            ]);
        }

        return $notification;
    }


    public static function list(array $data = [])
    {
        $builder = SendBulkNotification::latest();

        if (!empty($type = $data["type"] ?? null)) {
            $builder = $builder->where("type", $type);
        }

        if (!empty($status = $data["status"] ?? null)) {
            $builder = $builder->where("status", $status);
        }

        if (!empty($recipients = $data["recipients"] ?? null)) {
            $builder = $builder->whereHas('recipients', function ($query) use ($recipients) {
                $query->whereIn('user_id', $recipients);
            });
        }

        return $builder;
    }

    public function delete(string $id)
    {
        $notification = self::getById($id);
        $notification->recipients()->detach(); // Remove all recipients
        $notification->delete();
    }

    public function changeStatus(Request $request, $id)
    {
        $status = $request->input('status');
        if (!in_array($status, [StatusConstants::SENT, StatusConstants::PENDING])) {
            throw new InvalidRequestException("Invalid status provided");
        }

        $notification = $this->getById($id);
        $notification->update([
            "status" => $status
        ]);

    }
}
