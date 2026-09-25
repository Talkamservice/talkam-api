<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionBookedNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public $session, public string $audience = "user")
    {
        //
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    /**
     * The client's "your session is confirmed" moment and the therapist's
     * "you have a new request to review" moment are different in substance
     * (see the actionable `extra` below for the therapist's app-side accept/
     * decline), but both now get a branded template of their own rather
     * than the therapist falling back to the generic one.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);

        if ($this->audience !== "therapist") {
            $therapist_user = $this->session->therapist?->user;
            return (new MailMessage)
                ->subject($data["title"])
                ->view('emails.mobile.session-booked', [
                    "therapistName" => $therapist_user?->full_name,
                    "therapistShortName" => $therapist_user?->first_name,
                    "sessionDateTime" => SessionNotificationSupport::formatDateTime($this->session->starts_at),
                    "sessionFormat" => SessionNotificationSupport::formatLabel($this->session->format),
                    "sessionUrl" => SessionNotificationSupport::sessionUrl($this->session),
                ]);
        }

        // A business-covered booking (anything but "consumer") means this is
        // an employee the therapist is seeing through their employer's
        // network, not an anonymous public client — same distinction the web
        // dashboard's own clientRef() makes. Only a true consumer session
        // stays anonymized.
        $client_ref = $this->session->coverage
            && $this->session->coverage !== \App\Constants\Business\SessionCoverageConstants::CONSUMER
            && $this->session->user?->full_name
                ? $this->session->user->full_name
                : SessionNotificationSupport::anonRef($this->session->user_id);

        return (new MailMessage)
            ->subject($data["title"])
            ->view('emails.mobile.session-request', [
                "clientRef" => $client_ref,
                "sessionDateTime" => SessionNotificationSupport::formatDateTime($this->session->starts_at),
                "sessionFormat" => SessionNotificationSupport::formatLabel($this->session->format),
                "sessionUrl" => SessionNotificationSupport::sessionUrl($this->session),
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
        $when = $this->session->starts_at->format('D, M j \a\t g:ia');
        $message = $this->audience == "therapist"
            ? "You have a new confirmed session on $when."
            : "Your therapy session is confirmed for $when.";

        $data = [
            'data' => ['id' => $this->session->id],
            'title' => $this->audience == "therapist" ? "New Booking Request" : "Session Confirmed",
            'message' => $message,
            'link' => config("app.web_url"),
            'type' => 'therapy_session',
            'batch_no' => null,
        ];

        // Actionable feed item (§10): rides in `extra` (the feed resource
        // passes extra through) so the app renders Open Session / Decline.
        if ($this->audience == "therapist") {
            $data['extra'] = [
                'action' => [
                    'type' => 'booking_request',
                    'session_id' => $this->session->id,
                    'endpoints' => [
                        'request_sheet' => "/api/v2/therapist/sessions/{$this->session->id}/request",
                        'acknowledge' => "/api/v2/therapist/sessions/{$this->session->id}/acknowledge",
                        'decline' => "/api/v2/user/bookings/{$this->session->id}/cancel",
                    ],
                ],
            ];
        }

        return $data;
    }
}
