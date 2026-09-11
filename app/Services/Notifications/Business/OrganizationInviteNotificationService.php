<?php

namespace App\Services\Notifications\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Invitation;
use App\Services\Notifications\AppMailerService;
use Illuminate\Support\Str;

/**
 * Employee / therapist invite email — renders the dedicated TalkAM Design
 * templates (emails.business.employee-invite / therapist-invite) instead of
 * the generic emails.invitation.new used elsewhere in this file's history.
 */
class OrganizationInviteNotificationService
{
    public static function send(Invitation $invitation): void
    {
        $organization = $invitation->organization;
        $company = $organization?->name ?? "your company";
        $is_therapist = $invitation->invite_role === OrganizationConstants::ROLE_THERAPIST;
        $inviter = $invitation->inviter;
        $accept_url = self::acceptUrl($invitation);

        if ($is_therapist) {
            AppMailerService::send([
                "data" => [
                    "companyShortName" => self::shortName($company),
                    // No distinct verification destination exists yet — accepting
                    // the invite is where credential verification actually
                    // continues today, so this points at the same accept link.
                    "startVerificationUrl" => $accept_url,
                ],
                "to" => $invitation->invitee_email,
                "template" => "emails.business.therapist-invite",
                "subject" => "You're invited to join as a therapist",
            ]);
            return;
        }

        AppMailerService::send([
            "data" => [
                "inviterName" => $inviter?->full_name ?: "A teammate",
                "inviterFirstName" => $inviter?->first_name ?: "A teammate",
                "companyName" => $company,
                "companyShortName" => self::shortName($company),
                "acceptInviteUrl" => $accept_url,
            ],
            "to" => $invitation->invitee_email,
            "template" => "emails.business.employee-invite",
            "subject" => "You've been invited to TalkAM",
        ]);
    }

    /** Deep link to the web invite-landing screen (/business/join?token=...). */
    public static function acceptUrl(Invitation $invitation): string
    {
        $base = rtrim((string) config("business.web_url"), "/");

        return "{$base}/business/join?token=" . $invitation->uuid;
    }

    /** No short-name field exists on Organization — derive one for email copy. */
    private static function shortName(string $company): string
    {
        return Str::limit(Str::before($company, ' '), 24, '');
    }
}
