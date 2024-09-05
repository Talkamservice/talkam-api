<?php

namespace App\Notifications\User;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentsRemovedFromApplicationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $reported_comment)
    {

    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database', 'firebase'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data['title'])
            ->markdown('emails.comments.comment-removed', [
                'title' => $data['title'],
                'message' => $data['message'],
                'recipient_name' => $notifiable->getName(),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'post_comment_id' => $this->reported_comment->id,
            'title' => 'Comment Removed Notification',
            'message' => 'Your comment has been removed completely due to a guideline violation. For the safety of our community, deleted comments cannot be undone.',
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        return $this->buildData($notifiable);
    }

    /**
     * Get the Firebase representation of the notification.
     */
    public function toFirebase($notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data['title'])
            ->setBody($data['message'])
            ->setType($data['type'])
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    /**
     * Build the data for the notification.
     *
     * @return array<string, mixed>
     */
    protected function buildData($notifiable): array
    {
        return [
            'data' => [
                'id' => $this->reported_comment->comment?->post_id,
            ],
            'title' => 'Comment Removed Notification',
            'message' => 'Your comment has been removed completely due to a guideline violation. For the safety of our community, deleted comments cannot be undone.',
            'link' => null,
            'type' => 'comment',
            'batch_no' => null,
            'extra' => [],
        ];
    }
}
