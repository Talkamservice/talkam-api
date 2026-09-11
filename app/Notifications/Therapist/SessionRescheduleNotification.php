<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionRescheduleNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public $reschedule, public string $event = 'requested')
    {
        //
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    /**
     * Only the 'accepted' case matches the new template's "your session was
     * rescheduled" premise. 'requested' (please respond) and 'declined'
     * (request rejected) are different emails with no corresponding design —
     * they keep the existing generic template. Either party can be the
     * requester (SessionRescheduleService::request() has no role
     * restriction), so also guard against notifying the therapist with
     * client-facing "your session" copy about their own session.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        $session = $this->reschedule->session;
        $is_therapist_recipient = $session?->therapist?->user_id === $notifiable->id;

        if ($this->event === 'accepted' && !$is_therapist_recipient) {
            $therapist_user = $session?->therapist?->user;
            return (new MailMessage)
                ->subject($data["title"])
                ->view('emails.mobile.session-rescheduled', [
                    "therapistShortName" => $therapist_user?->first_name,
                    "previousDateTime" => SessionNotificationSupport::formatDateTime($this->reschedule->old_starts_at),
                    "newDateTime" => SessionNotificationSupport::formatDateTime($this->reschedule->new_starts_at),
                    "sessionFormat" => SessionNotificationSupport::formatLabel($session?->format),
                    "sessionUrl" => SessionNotificationSupport::sessionUrl($session),
                ]);
        }

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
        $new_time = $this->reschedule->new_starts_at->format('D, M j \a\t g:ia');

        $message = match ($this->event) {
            'accepted' => "Your reschedule request was accepted. The session now holds on $new_time.",
            'declined' => "Your reschedule request was declined. You can try another time.",
            default => "A reschedule to $new_time has been requested for your session.",
        };

        return [
            'data' => ['id' => $this->reschedule->session_id],
            'title' => "Session Reschedule",
            'message' => $message,
            'link' => config("app.web_url"),
            'type' => 'therapy_session',
            'batch_no' => null,
        ];
    }
}
