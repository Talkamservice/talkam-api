<?php

namespace App\Notifications\Post;

use App\Http\Resources\Post\PostCommentResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Users\UserResource;
use App\Models\UserPostReaction;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPostReactionNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $post_reaction)
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
        return ['mail', 'database', 'firebase'];
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
        $action_by = $this->post_reaction->user->username ?? $this->post_reaction->user->full_name;
        $total_actions = UserPostReaction::where("post_id", $this->post_reaction->post_id)
            ->where("action", $this->post_reaction->action)
            ->whereNot("user_id", $this->post_reaction->user_id)
            ->count();

        if ($total_actions == 0) {
            $message = "{$action_by} " . strtolower($this->post_reaction->action) . " your post.";
        }else {
            $message = "{$action_by} and {$total_actions} others " . strtolower($this->post_reaction->action) . " your post.";
        }
        return [
            'data' => [
                'id' => $this->post_reaction->post_id,
            ],
            'title' => "New {$this->post_reaction->action}",
            'message' => $message,
            'link' => null,
            'type' => 'post',
            'batch_no' => null,
            "extra" => [
                "user" => UserResource::custom($this->post_reaction->user),
            ]
        ];
    }
}
