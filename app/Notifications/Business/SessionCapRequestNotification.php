<?php

namespace App\Notifications\Business;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An employee hit their per-cycle session cap and asked their admin to act (web §03 Settings → Session Policy). */
class SessionCapRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $employeeName,
        public int $used,
        public ?int $cap
    ) {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $usage = $this->cap !== null
            ? "used all {$this->used} of their {$this->cap} sessions for this billing cycle"
            : "asked for more sessions this billing cycle";

        return (new MailMessage)
            ->subject("{$this->employeeName} needs more sessions")
            ->greeting("Hi {$notifiable->getName()},")
            ->line("{$this->employeeName} has {$usage} at {$this->organizationName}.")
            ->line("Approve more sessions or top up the shared pool from your Settings screen.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            "type" => "session_cap_request",
            "employee_name" => $this->employeeName,
            "used" => $this->used,
            "cap" => $this->cap,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
