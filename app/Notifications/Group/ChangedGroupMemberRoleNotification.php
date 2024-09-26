<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ChangedGroupMemberRoleNotification extends Notification
{
    use Queueable;


    public function __construct(public $group_member) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'firebase'];
    }

    public function toMail($notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject('Group Suspension Notification')
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
        // Determine if the user was made or removed as a Moderator
        $message = $this->group_member->role === 'Moderator'
            ? "You have been made a Moderator in {$this->group_member->group->name}"
            : "You have been removed from the Moderator role in {$this->group_member->group->name}";

        return [
            'data' => [
                'id' => $this->group_member->group_id,
            ],
            'title' => "Member Role Notice",
            'message' => $message, 
            'link' => null,
            'type' => 'notification',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
