<?php

namespace App\Notifications\Messaging;

use App\Http\Resources\Users\UserResource;
use App\Models\Message;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

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
        // v2 (§16): per-member conversation mute — stored since v1 but never
        // enforced — and §04 user-level mutes now suppress the notification.
        // Only changes behavior for users who muted, which is what they asked.
        $member = \App\Models\ConversationMember::where([
            "conversation_id" => $this->message->conversation_id,
            "user_id" => $notifiable->id,
        ])->first();

        $muted = !empty($member)
            && $member->is_muted
            && (empty($member->muted_until) || now()->lt($member->muted_until));

        $user_muted = \App\Models\UserMute::where([
            "user_id" => $notifiable->id,
            "muted_user_id" => $this->message->sender_id,
        ])->exists();

        if ($muted || $user_muted) {
            return [];
        }

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
            ->markdown('emails.message.index', [
                "title" => $data["title"],
                "message" => $data["message"],
                "userId" => $this->message->sender->id,
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
                'id' => $this->message->conversation_id,
                "type" => $data["type"],
                "id" => $this->message?->conversation_id,
                "type" => "conversation",
                "extra" => [
                    "sender" => UserResource::custom($this->message->sender)
                ]
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $title = "Message from " . $this->message->sender->getName();

        return [
            'data' => [
                'id' => $this->message->conversation_id,
            ],
            'title' => $title,
            'message' => "{$this->message->sender->getName()} sent you a message",
            'link' => null,
            'type' => 'conversation',
            'batch_no' => null,
            "extra" => [
                "sender" => UserResource::custom($this->message->sender)
            ]
        ];
    }
}
