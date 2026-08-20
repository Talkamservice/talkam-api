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
