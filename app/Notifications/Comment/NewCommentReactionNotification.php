<?php

namespace App\Notifications\Comment;

use App\Http\Resources\Post\PostCommentResource;
use App\Http\Resources\Users\UserResource;
use App\Models\UserCommentReaction;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class NewCommentReactionNotification extends Notification
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
                "type" => $data["type"],
                "extra" => [
                    "user" => UserResource::custom($this->comment_reaction->user),
                ]
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $action_by = $this->comment_reaction->user->username ?? $this->comment_reaction->user->full_name;
        $total_actions = UserCommentReaction::where("comment_id", $this->comment_reaction->coment_id)
            ->where("action", $this->comment_reaction->action)
            ->whereNot("user_id", $this->comment_reaction->user_id)
            ->get()
            ->unique("user_id")
            ->count();

        if ($total_actions == 0) {
            $message = "{$action_by} " . strtolower($this->comment_reaction->action) . " your comment.";
        } else {
            $message = "{$action_by} and {$total_actions} others " . strtolower($this->comment_reaction->action) . "d your comment.";
        }

        $web_url = config("app.web_url") . "/comment/{$this->comment_reaction->comment->post_id}";

        return [
            'data' => [
                'id' => $this->comment_reaction->comment->post_id,
            ],
            'title' => "{$this->comment_reaction->action}d" .' '. " comment",
            'message' => $message,
            'link' => $web_url,
            'type' => 'post',
            'batch_no' => null,
            "extra" => [
                "user" => UserResource::custom($this->comment_reaction->user),
            ]
        ];
    }
}
