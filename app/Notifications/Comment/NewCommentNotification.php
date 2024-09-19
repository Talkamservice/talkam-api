<?php

namespace App\Notifications\Comment;

use App\Http\Resources\Post\PostAttachmentResource;
use App\Http\Resources\Post\PostCommentResource;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class NewCommentNotification extends Notification
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
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $total_comments = $this->comment?->post?->comments()->whereNot("user_id", $this->comment->user_id)->distinct("user_id")->count();
        $commenter = ($this->comment->is_anonymous == 1) ? "Anonymous" : $this->comment->user->username ?? $this->comment->user->full_name;

        if ($total_comments == 0) {
            $message = "{$commenter} replied to your post.";
        }else {
            $message = "@{$commenter} and {$total_comments} others replied to your post.";
        }

        $web_url = config("app.web_url") . "/comment/{$this->comment->post_id}";

        return [
            'data' => [
                'id' => $this->comment->post_id,
            ],
            'title' => "Comment",
            'message' => $message,
            'link' => $web_url,
            'type' => 'comment',
            'batch_no' => null,
            "extra" => [
                "comment" => PostCommentResource::custom($this->comment),
                "post_attachements" => !empty($this->comment?->post?->attachments) ? PostAttachmentResource::collection($this->comment?->post?->attachments) : null
            ]
        ];
    }
}
