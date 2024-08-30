<?php

namespace App\Notifications\Messaging;

use App\Helpers\MethodsHelper;
use App\Http\Resources\Therapist\TherapistResource;
use App\Http\Resources\Users\UserResource;
use App\Models\Message;
use App\Models\User;
use App\Services\Message\FcmPushNotificationService;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kutia\Larafirebase\Messages\FirebaseMessage;

class NewMessageNotification extends Notification
{
    use Queueable;

    public $therapist;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Message $message)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ["mail", "database", "firebase"];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data["title"])
            ->markdown('emails.general.index', [
                "title" => $data["title"],
                "message" => $data["message"],
                "recipient_name" => $notifiable->getName(),
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
            ->setMetadata([
                "id" => $this->message?->conversation_id,
                "type" => "conversation",
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $title = "New Message from " . $this->message->sender->getName();

        return [
            'data' => [
                'id' => $this->message->conversation_id,
            ],
            'title' => $title,
            'message' => "{$this->message->sender->getName()} sent you a new message",
            'link' => null,
            'type' => 'conversation',
            'batch_no' => null,
            "extra" => [
                "sender" => UserResource::custom($this->message->sender)
            ]
        ];
    }
}
