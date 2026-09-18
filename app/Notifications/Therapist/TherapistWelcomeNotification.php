<?php

namespace App\Notifications\Therapist;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One-time "your dashboard is ready" email, sent the first time a therapist
 * loads GET /therapist/home — see TherapistDashboardService::home(), which
 * guards this with Therapist.welcome_email_sent_at (nothing else marks a
 * first visit). Kept synchronous like most notifications in this codebase,
 * deliberately not ShouldQueue — a stalled/absent queue worker on a given
 * environment must not silently swallow this.
 */
class TherapistWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(public $therapist)
    {
    }

    public function via(object $notifiable): array
    {
        return ["mail"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Welcome to your TalkAM dashboard")
            ->view('emails.business.therapist-welcome', [
                "therapistFirstName" => $notifiable->first_name ?: "there",
                "downloadUrl" => "https://apps.apple.com/us/app/talkam-tech/id6740508182",
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
