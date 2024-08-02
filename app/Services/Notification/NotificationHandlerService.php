<?php

namespace App\Services\Notification;


class NotificationHandlerService
{
    public function init($userId)
    {
        $this->user = User::find($userId);
        $this->setPreferences();
        $this->notification_repository = new NotificationRepository(new Notification);
        return $this;
    }

    public function setPreferences()
    {
        $this->can_send_email = $this->canSendNotification("Email") && !empty($this->user?->email);
        $this->can_send_push_notification = $this->canSendNotification("Push notifications");
        $this->can_send_sms = $this->canSendNotification("SMS");
    }

    private function canSendNotification($path)
    {
        $notification = $this->user->userNotificationPreference()->status()
            ->whereHas("notificationPreference", function ($notification_preference) use ($path) {
                $notification_preference->where("path", $path);
            })->first();

        return !empty($notification);
    }
}
