<?php

namespace App\Notifications\User;

use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Platform Admin Disputes → "Notify both parties by email upon
 *  resolution" — the first real writer here; nothing notified anyone
 *  about a dispute outcome before this checkbox existed. */
class DisputeResolvedNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public string $reference, public string $status, public string $resolution)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
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
                "action_url" => config("app.web_url"),
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
        $outcome = $this->status === "Resolved" ? "resolved" : "dismissed";

        return [
            'data' => ['reference' => $this->reference],
            'title' => "Dispute {$this->reference} has been {$outcome}",
            'message' => "Your dispute ({$this->reference}) has been {$outcome}. {$this->resolution}",
            'link' => config("app.web_url"),
            'type' => 'dispute_resolved',
            'batch_no' => null,
        ];
    }
}
