<?php

namespace App\Notifications\Promotion;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExpiredPromotionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $promotion, public $message) {}

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
            ->line("Expired At: " . $data['expired_at'])
            ->markdown('emails.promotion.expired', [
                'title' => $data['title'],
                'message' => $data['message'],
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
                    "type" => $this->promotion->type,
                ],
            ])
            ->initiate();
    }

    /**
     * Build notification data.
     */
    protected function buildData($notifiable): array
    {
        $expiresAt = Carbon::parse($this->promotion->created_at)->addDays($this->promotion->duration);
       
        $data = [
            'data' => [
                'id' => $this->promotion->id,
            ],
            'title' => "Your promotion has Expired",
            'message' => $this->message,
            'expired_at' => $expiresAt,
            'type' => 'promotion',
            'link' => null,
            'batch_no' => null,
            'extra' => []
        ];
        return $data;

        
    }
}
