<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public $payout)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

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

    public function buildData($notifiable)
    {
        $account = $this->payout->payoutAccount;
        $last4 = substr($account?->account_number ?? '', -4);

        return [
            'data' => ['id' => $this->payout->id],
            'title' => "Payout Received",
            'message' => format_money($this->payout->amount, 2, "₦")
                . " transferred to your bank account ending {$last4}.",
            'link' => config("app.web_url"),
            'type' => 'payout',
            'batch_no' => null,
        ];
    }
}
