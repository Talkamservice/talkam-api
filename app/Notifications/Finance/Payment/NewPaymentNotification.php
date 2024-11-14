<?php

namespace App\Notifications\Finance\Payment;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Helpers\MethodsHelper;
use App\Models\Payment;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kutia\Larafirebase\Messages\FirebaseMessage;

class NewPaymentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Payment $payment)
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
                "payment" => $this->payment,
                'recipient_name' => $notifiable->getName(),
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
                "id" => (string) $this->payment?->id,
                "type" => "payment",
                "extra" => []
            ])
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    public function buildData($notifiable)
    {
        return [
            'data' => [
                'id' => $this->payment?->promotion?->id,
            ],
            'title' => 'New Payment!',
            'message' => $this->buildMessage(),
            'link' => null,
            'type' => 'promotion',
            'batch_no' => null,
        ];
    }

    public function buildMessage()
    {
        if (in_array($this->payment->activity, [PaymentConstants::PAYMENT_FOR_PROMOTION])) {
            $message = "Your ad is live! Click to check your analytics for more insights";
        }

        return $message ?? "";
    }
}
