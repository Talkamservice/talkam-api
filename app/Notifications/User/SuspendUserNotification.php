<?php

namespace App\Notifications\User;

use App\Constants\General\StatusConstants;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class SuspendUserNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $user, public $status = null)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data["title"])
            ->markdown('emails.rules.index', [
                "title" => $data["title"],
                "message" => $data["message"],
                "recipient_name" => $notifiable->full_name,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase($notifiable)
    {
        return $this->buildData($notifiable);
    }

    public function toFirebase(object $notifiable)
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
        $title = $this->status == StatusConstants::ACTIVE ? "Supension Removed!" : "Suspension Received";
        $message = $this->status == StatusConstants::ACTIVE ? "Your suspension has been removed and your account is now active. Ensure to abide by Talkam's rules and policies." : "You have received a strike for failing to comply with Talkam's rules and policies.";
        return [
            'data' => [
                'id' => $this->user->id,
            ],
            'title' => $title,
            'message' => $message,
            'link' => null,
            'type' => 'user',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
