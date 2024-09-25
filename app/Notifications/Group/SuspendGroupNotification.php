<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class SuspendGroupNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(public $group, public $duration, public $reason)
    {
        // dd($group, $duration, $reason);
        // No need for extra assignment; public properties are automatically assigned
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'firebase'];
        // return MethodsHelper::userNotificationPreference($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data['title'])
            ->markdown('emails.group.suspend-group', [
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
        $message = "Your group has been suspended for {$this->duration} days.<br>The suspension is due to the following reason:<br>{$this->reason}.";
        return [
            'data' => [
                'id' => $this->group->id,
            ],
            'title' => "Group Suspension Notice!",
            'message' => $message,
            'link' => null,
            'type' => 'notification',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
