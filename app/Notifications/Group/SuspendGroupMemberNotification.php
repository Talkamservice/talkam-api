<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SuspendGroupMemberNotification extends Notification
{
    use Queueable;


    public function __construct(public $group_member, public $message) {}

    public function via($notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    public function toMail($notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject('Group Suspension Notification')
            ->markdown('emails.group.suspend-member', [
                "title" => $data["title"],
                "message" => $data["message"],
                'recipient_name' => $notifiable->full_name,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase($notifiable)
    {
        return $this->buildData($notifiable);
    }

    public function toFirebase($notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data["title"])
            ->setBody($data["message"])
            ->setType($data["type"])
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    public function buildData($notifiable)
    {
        return [
            'data' => [
                'id' => $this->group_member->group_id,
            ],
            'title' => "Suspension Notice",
            'message' => $this->message,
            'link' => null,
            'type' => 'notification',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
