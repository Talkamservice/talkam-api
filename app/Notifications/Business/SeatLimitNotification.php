<?php

namespace App\Notifications\Business;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Seats are running low (web §03 Settings → "Seat limit alerts"). */
class SeatLimitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public int $remaining,
        public int $seatsLicensed
    ) {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->organizationName} is running low on seats")
            ->greeting("Hi {$notifiable->getName()},")
            ->line("Only {$this->remaining} of {$this->seatsLicensed} licensed seats remain for {$this->organizationName}.")
            ->line("Add more seats from your Billing screen before you run out.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            "type" => "seat_limit_alert",
            "remaining" => $this->remaining,
            "seats_licensed" => $this->seatsLicensed,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
