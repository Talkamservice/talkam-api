<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionRescheduleNotification extends Notification
{
    use Queueable;

    public function __construct(public $reschedule, public string $event = 'requested')
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
