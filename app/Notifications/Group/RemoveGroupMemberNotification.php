<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class RemoveGroupMemberNotification extends Notification
{
    use Queueable;


    public function __construct(public $group_member, public $message) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'firebase'];
    }

    public function toMail($notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject('Memeber Remover Notice')
            ->markdown('emails.group.suspend-member', [
                "title" => $data["title"],
                "message" => $data["message"],
                'recipient_name' => $notifiable->getName(),
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
            'title' => "Memeber Remover Notice",
            'message' => "You have been removed from {$this->group_member->group->name} by the group moderator",
            'link' => null,
            'type' => 'notification',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
