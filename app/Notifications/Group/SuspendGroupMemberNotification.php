<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspendGroupMemberNotification extends Notification
{
    use Queueable;

    protected $message;

    public function __construct(public $user, $message)
    {
        $this->message = $message;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database', 'firebase'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Group Suspension Notification')
            ->markdown('emails.group.suspend-member', [
                'title' => 'Suspension Notification',
                'message' => $this->message,
                'recipient_name' => $notifiable->full_name,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => $this->message,
            'type' => 'suspension',
        ];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => $this->message,
            'type' => 'suspension',
        ];
    }

    public function toFirebase($notifiable)
    {
        return (new FirebaseNotificationService)
            ->setTitle('Suspension Notification')
            ->setBody($this->message)
            ->setType('suspension')
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    
}
