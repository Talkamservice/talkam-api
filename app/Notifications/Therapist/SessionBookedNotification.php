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

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
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
