<?php

namespace App\Notifications\Concerns;

use App\Services\Notifications\FirebaseNotificationService;

/**
 * Default toFirebase() for notifications whose buildData() already returns
 * the uniform {data, title, message, type, ...} shape used by toDatabase().
 * Classes with genuinely extra push metadata (e.g. embedding related
 * resources) should still define their own toFirebase() instead of this.
 */
trait SendsFirebasePush
{
    public function toFirebase(object $notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data["title"])
            ->setBody($data["message"])
            ->setType($data["type"])
            ->byUserToken($notifiable->fcm_token)
            ->setMetadata($data["data"] ?? [])
            ->initiate();
    }
}
