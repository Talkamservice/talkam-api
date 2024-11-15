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
            ->line("Expiration Date: " . Carbon::parse($this->promotion->created_at)->addDays($this->promotion->duration))
            ->line("Status: " . ucfirst($data['status']))
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
                'id' => $this->promotion->getModelTypeAttribute->id,
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
        // Calculate the promotion's expiration date
        $expiresAt = Carbon::parse($this->promotion->created_at)->addDays(3);
        $remainingDays = Carbon::now()->diffInDays($expiresAt, false); // Get remaining days (can be negative if expired)
        
        // Customizing message based on remaining time
        if ($remainingDays > 0) {
            $title = "Your promotion is pending and will expire soon";
            $message = "Your promotion is set to expire in {$remainingDays} day" . ($remainingDays > 1 ? 's' : '') . ". Please respond to ensure it remains active.";
        } else {
            $title = "Your promotion has expired";
            $message = "Your promotion expired {$remainingDays} day" . ($remainingDays < -1 ? 's' : '') . " ago. Please take action if you'd like to reactivate it.";
        }

        $data =  [
            'data' => [
                'id' => $this->promotion->getModelTypeAttribute->id,
            ],
            'title' => $title,
            'message' => $message,
            'expires_at' => $expiresAt->toDateString(),
            'status' => $this->promotion->status,
            'type' => 'promotion_expiry_reminder',
            'link' => null,
            'batch_no' => null,
            'extra' => []
        ];
        Log::info($notifiable, $data);
        return $data;
    }
}
