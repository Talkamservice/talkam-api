<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TherapistSessionRequestNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public $request, public string $event = 'submitted')
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
        $proposed_time = $this->request->proposed_starts_at?->format('D, M j \a\t g:ia');

        $message = match ($this->event) {
            'proposed' => "A therapist proposed $proposed_time for your session request.",
            'declined_by_therapist' => "Your session request couldn't be accommodated right now.",
            'declined_by_client' => "The client declined the proposed time.",
            default => "You have a new client session request.",
        };

        return [
            'data' => ['id' => $this->request->id, 'session_id' => $this->request->session_id],
            'title' => "Session Request",
            'message' => $message,
            'link' => config("app.web_url"),
            'type' => 'therapy_session_request',
            'batch_no' => null,
        ];
    }
}
