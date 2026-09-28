<?php

namespace App\Notifications\Post;

use App\Helpers\MethodsHelper;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewFollowedUserPostNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $post)
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

    public function toArray(object $notifiable): array
    {
        return [];
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
                'id' => $this->post->id,
                "type" => $data["type"],
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $author = $this->post->user->username ?? $this->post->user->full_name;
        $web_url = config("app.web_url") . "/post/{$this->post->id}";

        return [
            'data' => [
                'id' => $this->post->id,
            ],
            'title' => "New Post",
            'message' => "@{$author} shared a new post: " . ($this->post->title ?? "Check it out"),
            'link' => $web_url,
            'type' => 'followed_user_post',
            'batch_no' => null,
        ];
    }
}
