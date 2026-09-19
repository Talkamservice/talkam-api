<?php

namespace App\Notifications\Therapist;

use App\Constants\Therapist\TherapistConstants;
use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionAcknowledgedNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public $session)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    /**
     * An org-covered booking has no separate payment step — acknowledge()
     * IS what confirms it (SessionBookingService::confirmIfAwaitingReview()),
     * so this notification's recipient is having their "your session is
     * confirmed" moment right here, same premise as SessionBookedNotification's
     * branded template. A consumer session stays pending_payment regardless
     * of acknowledgement (the client still has to pay), so "your therapist
     * has seen it" is genuinely the right, weaker message there — keeps the
     * generic template.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);

        if ($this->session->status === TherapistConstants::SESSION_CONFIRMED) {
            $therapist_user = $this->session->therapist?->user;
            return (new MailMessage)
                ->subject("Session Confirmed")
                ->view('emails.mobile.session-booked', [
                    "therapistName" => $therapist_user?->full_name,
                    "therapistShortName" => $therapist_user?->first_name,
                    "sessionDateTime" => SessionNotificationSupport::formatDateTime($this->session->starts_at),
                    "sessionFormat" => SessionNotificationSupport::formatLabel($this->session->format),
                    "sessionUrl" => SessionNotificationSupport::sessionUrl($this->session),
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
        return [
            'data' => ['id' => $this->session->id],
            'title' => "Session Acknowledged",
            'message' => "Your therapist has seen your session booking for "
                . $this->session->starts_at->format('D, M j \a\t g:ia') . ".",
            'link' => config("app.web_url"),
            'type' => 'therapy_session',
            'batch_no' => null,
        ];
    }
}
