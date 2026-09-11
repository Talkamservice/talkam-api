<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionReminderNotification extends Notification
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
     * Sent to both the client and the therapist (SendSessionRemindersCommand)
     * with identical content today. The new template's client-facing framing
     * only fits the client recipient — the therapist keeps the generic one.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        $is_therapist_recipient = $this->session->therapist?->user_id === $notifiable->id;

        if (!$is_therapist_recipient) {
            $therapist_user = $this->session->therapist?->user;
            return (new MailMessage)
                ->subject($data["title"])
                ->view('emails.mobile.session-reminder', [
                    "therapistShortName" => $therapist_user?->first_name,
                    "sessionDateTime" => SessionNotificationSupport::formatDateTime($this->session->starts_at),
                    "sessionTime" => SessionNotificationSupport::formatTime($this->session->starts_at),
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
            'title' => "Session Reminder",
            'message' => "Your therapy session starts at " . $this->session->starts_at->format('g:ia') . ".",
            'link' => config("app.web_url"),
            'type' => 'therapy_session',
            'batch_no' => null,
        ];
    }
}
