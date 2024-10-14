<?php

namespace App\Notifications\Post;

use App\Http\Resources\Post\PostResource;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class NewThreadPostReactionNotification extends Notification
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
                    "post" => PostResource::custom($this->post_reaction->post),
                ]
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $web_url = config("app.web_url") . "/comment/{$this->post_reaction->post_id}";

        return [
            'data' => [
                'id' => $this->post_reaction->post_id,
            ],
            'title' => "{$this->post_reaction->action}d" .' '. "post",
            'message' => "A post just got {$this->post_reaction->action}d",
            'link' => $web_url,
            'type' => 'post',
            'batch_no' => null,
            "extra" => [
                "post" => PostResource::custom($this->post_reaction->post),
            ]
        ];
    }
}
