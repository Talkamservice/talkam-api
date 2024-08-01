<?php

namespace App\Services\Notification;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceService
{
    public static function getById($id): NotificationPreference
    {
        $notification_preference = NotificationPreference::find($id);
        if (empty($notification_preference)) {
            throw new ModelNotFoundException("Notification preference not found");
        }
        return $notification_preference;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "talkam_news" => "nullable|numeric|in:0,1",
            "talkam_research" => "nullable|numeric|in:0,1",
            "moderation_activities" => "nullable|numeric|in:0,1",
            "user_activities" => "nullable|numeric|in:0,1",
            "comments" => "nullable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["user_id"] = auth()->id();

        $notification_preference = NotificationPreference::firstOrCreate([
            "user_id" => $data["user_id"]
        ], $data);

        return $notification_preference;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $notification_preference = self::getById($id);
        $notification_preference->update($data);
        return $notification_preference->refresh();
    }

    public static function delete($notification_preference_id)
    {
        $notification_preference = self::getById($notification_preference_id);
        $notification_preference->delete();
    }

    public static function fetch($user_id)
    {
        $model = NotificationPreference::firstOrCreate(["user_id" => $user_id]);
        return $model;
    }
}
