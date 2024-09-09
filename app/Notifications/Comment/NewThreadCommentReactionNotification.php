<?php

namespace App\Notifications\Comment;

use App\Http\Resources\Post\PostAttachmentResource;
use App\Http\Resources\Post\PostCommentResource;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class NewThreadCommentReactionNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $comment_reaction)
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
            ->initiate();
    }

    public function buildData($notifiable)
    {
        return [
            'data' => [
                'id' => $this->comment_reaction?->comment?->post_id,
            ],
            'title' => "New {$this->comment_reaction->action}",
            'message' => "A comment just got {$this->comment_reaction->action}d",
            'link' => null,
            'type' => 'comment',
            'batch_no' => null,
            "extra" => [
                "comment" => PostCommentResource::custom($this->comment_reaction->comment),
                "post_attachements" => !empty($this->comment_reaction?->comment?->post?->attachments) ? PostAttachmentResource::collection($this->comment_reaction?->comment?->post?->attachments) : null
            ]
        ];
    }
}
