<?php

namespace App\Notifications\Comment;

use App\Http\Resources\Post\PostCommentResource;
use App\Models\UserCommentReaction;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class NewCommentMentionNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $comment)
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
                "action_url" => $data["link"]
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
                'id' => $this->comment->post_id,
                "type" => $data["type"],
                "extra" => [
                    "comment" => PostCommentResource::custom($this->comment),
                ]
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $commenter = ($this->comment->is_anonymous == 1) ? "Anonymous" : $this->comment->user->username ?? $this->comment->user->full_name;
        $reply_commenter = ($this->comment->repliedComment->is_anonymous == 1) ? "Anonymous" : $this->comment->repliedComment->user->getName();
        $message = "@{$commenter} replied: @{$reply_commenter} \"{$this->comment->comment}\"";

        $web_url = config("app.web_url") . "/comment/{$this->comment->post_id}";

        return [
            'data' => [
                'id' => $this->comment->post_id,
            ],
            'title' => "Comment thread",
            'message' => $message,
            'link' => $web_url,
            'type' => 'mention',
            'batch_no' => null,
            "extra" => [
                "comment" => PostCommentResource::custom($this->comment),
            ]
        ];
    }
}
