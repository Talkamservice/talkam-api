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
        public array $summary,
        public array $extra = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sessions = $this->summary["sessions_completed"];
        $topics = $this->summary["top_topics"];

        return (new MailMessage)
            ->subject("Your {$this->periodLabel} usage digest")
            ->view('emails.business.monthly-digest', [
                "companyShortName" => $this->extra["companyShortName"] ?? $this->organizationName,
                "sessionsBooked" => $sessions["suppressed"] ? null : $sessions["value"],
                "engagementRate" => $this->extra["engagementRate"] ?? null,
                "topTheme" => (!$topics["suppressed"] && !empty($topics["value"]))
                    ? $topics["value"][0]["label"]
                    : null,
                "reportUrl" => $this->extra["reportUrl"] ?? config("business.web_url"),
            ]);
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
