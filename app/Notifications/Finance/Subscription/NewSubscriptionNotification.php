<?php

namespace App\Notifications\Finance\Subscription;

use App\Models\Subscription;
use App\Services\Message\FcmPushNotificationService;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kutia\Larafirebase\Messages\FirebaseMessage;

class NewSubscriptionNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Subscription $subscription)
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
        return ['firebase', 'database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data["title"])
            ->markdown('emails.template.v1.subscription.new', [
                "title" => $data["title"],
                "message" => $data["message"],
                "plan" => $this->subscription->plan,
                "recipient_name" => $notifiable->name,
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
            ->setMetadata([
                "id" => (string) $this->subscription?->id,
                "type" => "subscription",
                "extra" => json_encode([])
            ])
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $message = "Thank you for subscribing to our {$this->subscription->plan->name} plan at Mentra. Your unwavering support is greatly appreciated!";
        return [
            'data' => [
                'id' => $this->subscription->id,
            ],
            'title' => 'New Subscription!',
            'message' => $message,
            'link' => null,
            'type' => 'subscription',
            'batch_no' => null,
        ];
    }
}
