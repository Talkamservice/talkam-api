<?php

namespace App\Services\Notifications\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Invitation;
use App\Services\Notifications\AppMailerService;

/**
 * Employee / therapist invite email, following the shape of
 * Invitation\InvitationNotificationService.
 *
 * NOTE: this uses the generic emails.invitation.new template. The designed
 * "Employee invite" / "Therapist invite" templates in
 * "TalkAM Email Templates.dc.html" are a content prerequisite — swapping the
 * template path here is the only change needed once that copy is signed off.
 */
class OrganizationInviteNotificationService
{
    public static function send(Invitation $invitation): void
    {
        $organization = $invitation->organization;
        $company = $organization?->name ?? "your company";
        $is_therapist = $invitation->invite_role === OrganizationConstants::ROLE_THERAPIST;

        $message = $is_therapist
            ? "{$company} has invited you to join TalkAM as a verified therapist. Click below to set up your account — you'll then complete a short credential verification before accepting sessions."
            : "{$company} has invited you to join TalkAM, a private mental-wellness benefit. Click below to set up your account. Your employer only ever sees anonymised, company-wide numbers — never your individual activity.";

        AppMailerService::send([
            "data" => [
                "title" => "You're invited to TalkAM by {$company}",
                "message" => $message,
                "recipient_name" => null,
                "action_url" => self::acceptUrl($invitation),
                "action_text" => "Accept Invitation",
            ],
            "to" => $invitation->invitee_email,
            "template" => "emails.invitation.new",
            "subject" => "You're invited to TalkAM by {$company}",
        ]);
    }

    /** Deep link to the web invite-landing screen (/business/join?token=...). */
    public static function acceptUrl(Invitation $invitation): string
    {
        $base = rtrim((string) config("business.web_url"), "/");

        return "{$base}/business/join?token=" . $invitation->uuid;
    }
}
