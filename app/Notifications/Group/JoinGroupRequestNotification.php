<?php

namespace App\Notifications\Group;

use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage; use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;

class JoinGroupRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $member)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject($data["title"])
            ->markdown('emails.general.index', [
                "title" => $data["title"],
                "message" => $data["message"],
                "recipient_name" => $notifiable->getName(),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase($notifiable)
    {
        return $this->buildData($notifiable);
    }

    public function toFirebase(object $notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data["title"])
            ->setBody($data["message"])
            ->setType($data["type"])
            ->byUserToken($notifiable->fcm_token)
            ->setMetadata([
                "extra" => []
            ])
            ->initiate();
    }

    public function buildData($notifiable)
    {
        $web_url = config("app.web_url") . "/group/{$this->member->group->id}";
        return [
            'data' => [
                'id' => $this->member->group_id,
            ],
            'title' => "Group Request",
            'message' => "{$this->member->user->getName()} has requested to join \"{$this->member->group->name}\". Kindly go to the request page to accept or decline.",
            'link' => $web_url,
            'type' => 'group_request',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
