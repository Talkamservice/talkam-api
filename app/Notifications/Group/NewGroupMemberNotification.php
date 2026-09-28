<?php

namespace App\Notifications\Group;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when someone is added to a group directly (an admin adding them, on
 * the platform-admin "Add Members" picker — the only caller of
 * GroupMemberService::addNewAdmin()), as opposed to
 * JoinGroupRequestNotification, which is the other direction: a member
 * asking to join, notifying the group's own admins.
 *
 * Deliberately not ShouldQueue, matching TherapistWelcomeNotification's own
 * reasoning — a stalled/absent queue worker in a given environment must not
 * silently swallow a "you've been added" email.
 */
class NewGroupMemberNotification extends Notification
{
    use Queueable;

    public function __construct(public $member)
    {
    }

    public function via(object $notifiable): array
    {
        return ["mail"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $group = $this->member->group;

        return (new MailMessage)
            ->subject("You've been added to \"{$group->name}\"")
            ->markdown('emails.general.index', [
                "title" => "You've been added to \"{$group->name}\"",
                "message" => "You're now a{$this->article()} {$this->member->role} of \"{$group->name}\" on TalkAM.",
                "recipient_name" => $notifiable->getName(),
                "action_url" => config("app.web_url") . "/group/{$group->id}",
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    /** "an Admin" vs "a Member" — the role name's own first letter decides. */
    private function article(): string
    {
        return in_array(strtolower(substr($this->member->role, 0, 1)), ['a', 'e', 'i', 'o', 'u'], true) ? 'n' : '';
    }
}
