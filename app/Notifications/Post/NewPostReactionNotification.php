<?php

namespace App\Notifications\Post;

use App\Http\Resources\Post\PostAttachmentResource;
use App\Http\Resources\Users\UserResource;
use App\Models\UserPostReaction;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
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
                'id' => $this->post_reaction->post_id,
                "type" => $data["type"],
                "extra" => [
                   "user" => UserResource::custom($this->post_reaction->user),
                "post_attachements" => !empty($this->post_reaction?->post?->attachments) ? PostAttachmentResource::collection($this->post_reaction?->post?->attachments) : null
                ]
            ])
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
            $message = "{$action_by} " . strtolower($this->post_reaction->action) . "d your post.";
        }else {
            $message = "{$action_by} and {$total_actions} others " . strtolower($this->post_reaction->action) . "d your post.";
        }

        $web_url = config("app.web_url") . "/comment/{$this->post_reaction->post_id}";

        return [
            'data' => [
                'id' => $this->post_reaction->post_id,
            ],
            'title' => "{$this->post_reaction->action}d" .' '. "post",
            'message' => $message,
            'link' => $web_url,
            'type' => 'post',
            'batch_no' => null,
            "extra" => [
                "user" => UserResource::custom($this->post_reaction->user),
                "post_attachements" => !empty($this->post_reaction?->post?->attachments) ? PostAttachmentResource::collection($this->post_reaction?->post?->attachments) : null
            ]
        ];
    }
}
