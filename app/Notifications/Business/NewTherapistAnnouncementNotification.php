<?php

namespace App\Notifications\Business;

use App\Models\Therapist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A new therapist joined TalkAM's verified network (web §03 Settings → "New
 * therapist announcements"). Platform-wide, cross-org — sent to every admin
 * who opted in, regardless of which company they run. Only the therapist's
 * already-public directory fields (name, credential, specialties) are used.
 */
class NewTherapistAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Therapist $therapist)
    {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->therapist->user?->full_name ?? "A new therapist";
        $credential = $this->therapist->credential_type;

        return (new MailMessage)
            ->subject("A new therapist joined TalkAM's network")
            ->greeting("Hi {$notifiable->getName()},")
            ->line("{$name}" . ($credential ? " ({$credential})" : "") . " just joined TalkAM's verified therapist network.")
            ->line("Your team can now book sessions with them if you use TalkAM's therapist network.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            "type" => "new_therapist_announcement",
            "therapist_id" => $this->therapist->id,
            "name" => $this->therapist->user?->full_name,
            "credential_type" => $this->therapist->credential_type,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
