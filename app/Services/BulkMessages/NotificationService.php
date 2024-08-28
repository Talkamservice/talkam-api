<?php

namespace App\Services\bulkMessages;

use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Jobs\SendUserNotificationJob;
use App\Models\SendBulkNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
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
            'type' => 'required|in:single,multiple,all',
            'status' => 'nullable|string',
            'schedule_date' => 'nullable|date',
            'user_id' => [
                'required_if:type,single,multiple', // required for 'single' and 'multiple'
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
        $sendAt = isset($data['schedule_date']) ? Carbon::parse($data['schedule_date']) : null;
    
        $jobs = [];
    
        // Handle existing or new notification
        if ($notificationId) {
            $notification = self::getById($notificationId);
            $notification->update($data);
        } else {
            $notification = SendBulkNotification::create($data);
        }
    
        // Determine recipients based on type
        if ($data['type'] === 'single' || $data['type'] === 'multiple') {
            // Ensure user_id is an array
            $userIds = is_array($data['user_id']) ? $data['user_id'] : [$data['user_id']];
            // Sync recipients
            $notification->recipients()->sync($userIds);
        } elseif ($data['type'] === 'all') {
            // Get all active user IDs
            $userIds = User::where('status', StatusConstants::ACTIVE)->pluck('id')->toArray();
            // Sync recipients
            $notification->recipients()->sync($userIds);
        }
    
        // Only dispatch jobs if the status is not pending
        if ($data['status'] !== StatusConstants::PENDING) {
            // Get the users for the notification
            $userIds = $notification->recipients()->pluck('user_id')->toArray();
            $users = User::whereIn('id', $userIds)->get();
    
            foreach ($users as $user) {
                $job = new SendUserNotificationJob($user, $notification);
                
                if ($sendAt && $sendAt->isFuture()) {
                    // Calculate the delay in seconds
                    $delayInSeconds = $sendAt->diffInSeconds(Carbon::now());
                    $job->delay($delayInSeconds);
                } 
    
                $jobs[] = $job;
            }
    
            // Dispatch all jobs using batching
            if (!empty($jobs)) {
                Bus::batch($jobs)
                    ->dispatch();
            }
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
}
