<?php

namespace App\Notifications\Therapist;

use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Platform Admin "Performance Watch" → "Send Warning Email". */
class TherapistPerformanceWarningNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public float $avgRating, public ?string $note = null)
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
        $message = "Your recent client ratings average {$this->avgRating}/5, below our quality standard. "
            . "Please review recent session feedback and reach out to support if you'd like guidance.";

        if (!empty($this->note)) {
            $message .= " Note from our team: {$this->note}";
        }

        return [
            'data' => [],
            'title' => "A note about your recent session ratings",
            'message' => $message,
            'link' => config("app.web_url"),
            'type' => 'therapist_performance_warning',
            'batch_no' => null,
        ];
    }
}
