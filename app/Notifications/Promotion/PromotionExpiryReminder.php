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

class PromotionExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $promotion)
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
            ->line("Expiration Date: " . carbon()->parse($this->promotion->created_at)->addDays($this->promotion->duration))
            ->line("Status: " . ucfirst($data['status']))
            // ->action('View Promotion', url('/promotions/' . $this->promotion->id))
            ->markdown('emails.bulk.notification', [
                'title' => $data['title'],
                'message' => $data['message'],
                'expiration_date' => $data['expires_at'],
                'status' => $data['status'],
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
                'id' => $this->promotion->id,
                'type' => $data['type'],
                'extra' => [],
            ])
            ->initiate();
    }

    /**
     * Build notification data.
     */
    protected function buildData($notifiable): array
    {
        $expiresAt = Carbon::parse($this->promotion->created_at)->addDays($this->promotion->duration);
        $remainingDays = Carbon::now()->diffInDays($expiresAt);
        
        $data = [
            'data' => [
                'id' => $this->promotion->id,
                'expires_at' => $remainingDays,
                'status' => $this->promotion->status,
            ],
            'title' => "Your promotion is pending and will expire soon",
            'message' => "Your promotion is set to expire on {$remainingDays}. Please respond to ensure it remains active.",
            'expires_at' => $remainingDays,
            'status' => $this->promotion->status,
            'type' => 'promotion_expiry_reminder',
            'link' => null,
            'batch_no' => null,
            'extra' => []
        ];
        return $data;
    }
}
