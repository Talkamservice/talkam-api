<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionCancelledNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public $session, public bool $refunded = false)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    /**
     * The new template is written for the client's "your session was
     * cancelled" moment (refund status, rebook CTA). When the notified
     * counterpart is actually the therapist for this session, that framing
     * doesn't fit — keep the existing generic template for them.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        $is_therapist_recipient = $this->session->therapist?->user_id === $notifiable->id;

        if (!$is_therapist_recipient) {
            $therapist_user = $this->session->therapist?->user;
            return (new MailMessage)
                ->subject($data["title"])
                ->view('emails.mobile.session-cancelled', [
                    "therapistShortName" => $therapist_user?->first_name,
                    "sessionDateTime" => SessionNotificationSupport::formatDateTime($this->session->starts_at),
                    "refundSummary" => $this->refunded ? "Fully refunded" : "No refund",
                    "rebookUrl" => SessionNotificationSupport::rebookUrl($this->session),
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
        $when = $this->session->starts_at->format('D, M j \a\t g:ia');
        $message = "The session scheduled for $when has been cancelled."
            . ($this->refunded ? " A full refund has been initiated." : "");

        return [
            'data' => ['id' => $this->session->id],
            'title' => "Session Cancelled",
            'message' => $message,
            'link' => config("app.web_url"),
            'type' => 'therapy_session',
            'batch_no' => null,
        ];
    }
}
