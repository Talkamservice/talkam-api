<?php

namespace App\Notifications\User;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class PostsRemovedFromApplicationNotification extends Notification
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
            ->markdown('emails.posts.suspended', [
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
            'post_id' => $this->post->id,
            'title' => 'Post(s) Removed Notification',
            'message' => 'Your posts has been removed completely due to a guideline violation. For safety of our community, deleted post(s) can never be undo',
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
                'id' => $this->post->id,
                "type" => $data["type"],
                "extra" => []
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        return [
            'data' => [
                'id' => $this->post->id,
            ],
            'title' => "Post(s) Removed Notification",
            'message' => "Your posts has been removed completely due to a guideline violation. For safety of our community, deleted post(s) can never be undo.",
            'link' => null,
            'type' => 'notification',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
