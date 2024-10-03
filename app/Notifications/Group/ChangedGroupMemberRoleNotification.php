<?php

namespace App\Notifications\Group;

use App\Constants\Account\User\UserConstants;
use App\Services\Notifications\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use App\Helpers\MethodsHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ChangedGroupMemberRoleNotification extends Notification
{
    use Queueable;


    public function __construct(public $group_member, public $oldRole) {}

    public function via($notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    public function toMail($notifiable): MailMessage
    {
        $data = $this->buildData($notifiable);
        return (new MailMessage)
            ->subject('Member Role Notice')
            ->markdown('emails.group.suspend-member', [
                "title" => $data["title"],
                "message" => $data["message"],
                'recipient_name' => $notifiable->getName(),
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase($notifiable)
    {
        return $this->buildData($notifiable);
    }

    public function toFirebase($notifiable)
    {
        $data = $this->buildData($notifiable);

        return (new FirebaseNotificationService)
            ->setTitle($data["title"])
            ->setBody($data["message"])
            ->setType($data["type"])
            ->byUserToken($notifiable->fcm_token)
            ->initiate();
    }

    public function buildData($notifiable)
    {
        // Determine if the user was made or removed as a Moderator
        $message = '';

        if ($this->oldRole === UserConstants::ADMIN && $this->group_member->role !== UserConstants::ADMIN) {
            $message = "You have been removed from the Moderator role in {$this->group_member->group->name}.";
        } elseif ($this->oldRole !== UserConstants::ADMIN && $this->group_member->role === UserConstants::ADMIN) {
            $message = "You have been made a Moderator in {$this->group_member->group->name}.";
        } else {
            $message = "Your role in {$this->group_member->group->name} has been changed.";
        }
        $web_url = config("app.web_url") . "/group/{$this->group_member->group->id}";
        return [
            'data' => [
                'id' => $this->group_member->group_id,
            ],
            'title' => "Member Role Notice",
            'message' => $message, 
            'link' => $web_url,
            'type' => 'group',
            'batch_no' => null,
            "extra" => []
        ];
    }
}
