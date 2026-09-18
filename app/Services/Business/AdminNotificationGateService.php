<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Support\Collection;

/**
 * Which admins are subscribed to a given notification-preference toggle (web
 * §03 Settings). Shared by every admin-notification sender so the "does this
 * admin want this email" check lives in exactly one place.
 */
class AdminNotificationGateService
{
    /** Active admins of one org, filtered to those subscribed to $preferenceKey. */
    public static function subscribedAdminsForOrg(
        Organization $organization,
        string $preferenceKey,
        bool $defaultOn = true
    ): Collection {
        $admins = OrganizationMember::where("organization_id", $organization->id)
            ->where("role", OrganizationConstants::ROLE_ADMIN)
            ->where("status", OrganizationConstants::MEMBER_ACTIVE)
            ->with("user")
            ->get()
            ->pluck("user")
            ->filter();

        return self::filterBySubscription($admins, $preferenceKey, $defaultOn);
    }

    /**
     * Every active admin PLATFORM-WIDE (across all orgs), filtered to those
     * subscribed to $preferenceKey. Deduped — an admin on more than one org's
     * account is only notified once.
     */
    public static function subscribedAdminsPlatformWide(string $preferenceKey, bool $defaultOn = true): Collection
    {
        $admins = OrganizationMember::where("role", OrganizationConstants::ROLE_ADMIN)
            ->where("status", OrganizationConstants::MEMBER_ACTIVE)
            ->with("user")
            ->get()
            ->pluck("user")
            ->filter()
            ->unique("id");

        return self::filterBySubscription($admins, $preferenceKey, $defaultOn);
    }

    private static function filterBySubscription(Collection $admins, string $key, bool $defaultOn): Collection
    {
        if ($admins->isEmpty()) {
            return $admins;
        }

        $prefs = NotificationPreference::whereIn("user_id", $admins->pluck("id"))
            ->get()
            ->keyBy("user_id");

        return $admins->filter(function ($admin) use ($prefs, $key, $defaultOn) {
            $pref = $prefs->get($admin->id)?->$key;
            return is_null($pref) ? $defaultOn : (bool) $pref;
        })->values();
    }
}
