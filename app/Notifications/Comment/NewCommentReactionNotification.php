<?php

namespace App\Notifications\Comment;

use App\Http\Resources\Post\PostCommentResource;
use App\Models\UserCommentReaction;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        $action_by = $this->comment_reaction->user->username ?? $this->comment_reaction->user->full_name;
        $total_actions = UserCommentReaction::where("comment_id", $this->comment_reaction->coment_id)
            ->where("action", $this->comment_reaction->action)
            ->count();

        $message = "{$action_by} and {$total_actions} others " . strtolower($this->comment_reaction->action) . " your comment.";

        return [
            'data' => [
                'id' => $this->comment_reaction->id,
            ],
            'title' => "New {$this->comment_reaction->action}",
            'message' => $message,
            'link' => null,
            'type' => 'comment_reaction',
            'batch_no' => null,
            "extra" => [
                "comment" => PostCommentResource::custom($this->comment_reaction->comment),
            ]
        ];
    }
}
