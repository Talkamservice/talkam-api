<?php

namespace App\Notifications\User;

use App\Helpers\MethodsHelper;
use App\Models\WellnessNudgeMessage;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WellnessCheckinNudgeNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    // Picked once per notification so the mail, database and push copies
    // of the same nudge all carry the same text.
    private string $title = "TalkAM Wellness Check-in";
    private string $message = "You haven't logged your mood today. How are you feeling?";

    public function __construct()
    {
        // Random pick from the admin-managed pool; the fixed text above is
        // the fallback when the pool is empty or has nothing active.
        if ($picked = WellnessNudgeMessage::random()) {
            $this->title = $picked->title;
            $this->message = $picked->message;
        }
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
            'data' => [],
            'title' => $this->title,
            'message' => $this->message,
            'link' => config("app.web_url"),
            'type' => 'wellness_nudge',
            'batch_no' => null,
        ];
    }
}
