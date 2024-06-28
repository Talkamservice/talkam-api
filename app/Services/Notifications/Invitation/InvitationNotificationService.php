<?php

namespace App\Services\Notifications\Invitation;

use App\Models\Invitation;
use App\Services\Notifications\AppMailerService;

class InvitationNotificationService
{
    public static function send(Invitation $invite)
    {
        $sender_name = $invite->inviter->name ?? "Talkam admin";
        $data = self::buildData($invite, $sender_name);
        $message = "You have been invited by {$sender_name} to access some exciting content on their Talkam admin account. Click the button below to accept the invite. ";

        AppMailerService::send([
            "data" => [
                "title" => $data["title"],
                "message" => $message,
                "recipient_name" => null,
                "action_url" => $data["link"],
                "action_text" => "View Invitation"
            ],
            "to" => $invite->invitee_email,
            "template" => "emails.invitation.new",
            "subject" => "Talkam Admin Invite",
        ]);
    }

    public static function buildData($invitation, $sender_name)
    {
        return [
            'data' => [
                'id' => $invitation->id,
            ],
            'title' => 'Talkam Admin Invite',
            'message' => "You have been invited by {$sender_name} to access some exciting content on their Talkam admin account. Check your email for the invite.",
            'link' => $invitation->callbackUrl(),
            'type' => 'invitation',
            'batch_no' => null,
        ];
    }
}
