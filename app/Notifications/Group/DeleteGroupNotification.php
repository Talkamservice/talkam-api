<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class DeleteGroupNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(public $group)
    {
         // No need for extra assignment; public properties are automatically assigned
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data['title'])
            ->markdown('emails.group.delete-group', [
                'title' => $data['title'],
                'message' => $data['message'],
                "group_name" => $this->group->name,
                'recipient_name' => $notifiable->getName(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->buildData($notifiable);
    }

    public function toDatabase($notifiable)
    {
        return $this->buildData($notifiable);
    }

    public function toFirebase(object $notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data['title'])
            ->setBody($data['message'])
            ->setType($data['type'])
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    protected function buildData($notifiable): array
    {
        return [
            'data' => [
                'id' => $this->group->id,
            ],
            'title' => "Group Deletion Notification!",
            'message' => "Your group '{$this->group->name}' has been Deleted due to reported violations",
            'link' => null,
            'type' => 'notification',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
