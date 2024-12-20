<?php

namespace App\Notifications\Promotion;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use App\Services\Promotion\PromotionService;
use Illuminate\Notifications\Notification;

class PromotionImpressionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $promotion, public $message, public $title = null)
    {
    }

    /**
     * Get the notification's delivery channels.
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
            ->subject($data['title'])
            ->line($data['message'])
            ->line("Total Impressions: " . $this->promotion->statAttribute('impressions'))
            ->line("Promotion Status: " . ucfirst($data['status']))
            ->markdown('emails.promotion.impression', [
                'title' => $data['title'],
                'message' => $data['message'],
                'impressions' => $this->promotion->statAttribute('impressions'),
                'recipient_name' => $notifiable->getName(),
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->buildData($notifiable);
    }

    public function toDatabase($notifiable)
    {
        return $this->buildData($notifiable);
    }

    public function toFirebase(object $notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data['title'])
            ->setBody($data['message'])
            ->setType($data['type'])
            ->byUserToken($notifiable->fcm_token)
            ->setMetadata([
                'id' => $this->promotion->getModelTypeAttribute->id,
                'type' => $data['type'],
                'extra' => [
                    "type" => $this->promotion?->type(),
                ],
            ])
            ->initiate();
    }

    /**
     * Build notification data.
     */
    protected function buildData($notifiable): array
    {
        $impressions = (new PromotionService)
                    ->promotionStats($this->promotion, "impressions");

        $data = [
            'data' => [
                'id' => $this->promotion->id,
            ],
            'title' => $this->title ?? "Your promotion has reached {$impressions} impressions",
            'message' => $this->message,
            'type' => 'promotion',
            'link' => null,
            'batch_no' => null,
            'extra' => []
        ];
        return $data;
    }
}
