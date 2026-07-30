<?php

namespace App\Notifications\Business;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The monthly usage-digest email (web §03 Settings → "Monthly usage digest").
 * Company-wide, anonymised figures only — the same suppression rule the admin
 * dashboard itself enforces, since this is the same aggregate data by mail.
 */
class OrganizationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $periodLabel,
        public array $summary
    ) {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sessions = $this->summary["sessions_completed"];
        $active = $this->summary["active_members"];

        $mail = (new MailMessage)
            ->subject("Your {$this->periodLabel} usage digest")
            ->greeting("Hi {$notifiable->getName()},")
            ->line("Here's how {$this->organizationName} used TalkAM in {$this->periodLabel}.")
            ->line("Seats: {$this->summary['seats_used']} of {$this->summary['seats_total']} filled")
            ->line($sessions["suppressed"]
                ? "Sessions completed: not enough activity yet to report"
                : "Sessions completed: {$sessions['value']}")
            ->line($active["suppressed"]
                ? "Active members: not enough activity yet to report"
                : "Active members: {$active['value']}");

        $topics = $this->summary["top_topics"];
        if (!$topics["suppressed"] && !empty($topics["value"])) {
            $labels = collect($topics["value"])->pluck("label")->take(3)->implode(", ");
            $mail->line("Top topics: {$labels}");
        }

        return $mail->line("See the full breakdown any time on your Overview dashboard.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            "type" => "organization_digest",
            "period" => $this->periodLabel,
            "summary" => $this->summary,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
