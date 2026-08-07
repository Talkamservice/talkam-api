<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public $session)
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
