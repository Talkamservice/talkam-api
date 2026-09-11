<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Business\SessionCapRequestNotification;
use Illuminate\Support\Facades\Notification;

/**
 * The admin-set "max sessions per employee, per billing cycle" policy (web
 * §03 Settings → Session Policy). This is a raw per-employee booking count,
 * not a bundle-drawdown decision, so — unlike CoverageResolver/
 * BundleLedgerService — it applies regardless of `business.coverage_enabled`.
 */
class SessionCapService
{
    /** Null when the user isn't an active org employee right now. */
    public static function status(User $user): ?array
    {
        $membership = $user->organizationMemberships()
            ->where("status", OrganizationConstants::MEMBER_ACTIVE)
            ->where("role", OrganizationConstants::ROLE_EMPLOYEE)
            ->with("organization")
            ->first();

        $org = $membership?->organization;

        if (!$org) {
            return null;
        }

        $cap = $org->per_employee_session_quota !== null
            ? (int) $org->per_employee_session_quota
            : null;

        // Scoped by when the booking was MADE, not the (often future, possibly
        // next-month) slot it's for — otherwise a booking made now for next
        // week would dodge this cycle's cap whenever that week rolls into the
        // next calendar month.
        $used = TherapySession::where("user_id", $user->id)
            ->active()
            ->whereBetween("created_at", [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return ["organization" => $org, "cap" => $cap, "used" => $used];
    }

    public static function enforceBeforeBooking(User $user): void
    {
        $status = self::status($user);

        if (!$status || $status["cap"] === null) {
            return;
        }

        if ($status["used"] >= $status["cap"]) {
            throw new InvalidRequestException(
                "You've used {$status['used']} of your {$status['cap']} sessions allowed this billing cycle. "
                . "Ask your admin to raise the cap or top up."
            );
        }
    }

    /** The employee-triggered "notify my admin" action from the cap-reached modal. */
    public static function requestTopUp(User $user): array
    {
        $status = self::status($user);

        if (!$status) {
            throw new InvalidRequestException("You're not part of a company account.");
        }

        $org = $status["organization"];
        $recipients = AdminNotificationGateService::subscribedAdminsForOrg($org, "session_cap_requests");

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new SessionCapRequestNotification($org->name, $user->full_name, $status["used"], $status["cap"])
            );
        }

        return $status;
    }
}
