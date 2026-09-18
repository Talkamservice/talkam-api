<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutReceivedNotification extends Notification
{
    use Queueable, SendsFirebasePush;

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
        return (new MailMessage)
            ->subject("Payout processed")
            ->view('emails.business.payout-processed', [
                "payoutAmount" => format_money($this->payout->amount, 2, "₦"),
                "earningsUrl" => config("app.web_url") . "/earnings",
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
