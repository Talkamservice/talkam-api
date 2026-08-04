<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;

/**
 * The two Danger Zone sweeps (web §03 Settings): cancellations landing, and
 * scheduled deletions purging. Both run daily; both are idempotent re-runs
 * (a cancelled/purged org no longer matches the sweep's own query).
 */
class OrganizationLifecycleService
{
    /**
     * Flips organizations whose cancellation date has passed to
     * STATUS_CANCELLED. No other code needs to change for this to take
     * effect — the monthly billing run and every access check already treat
     * anything other than STATUS_ACTIVE as inactive.
     */
    public static function processCancellations(): array
    {
        $orgs = Organization::where("status", OrganizationConstants::STATUS_ACTIVE)
            ->whereNotNull("cancels_at")
            ->where("cancels_at", "<=", now())
            ->get();

        foreach ($orgs as $org) {
            $org->update(["status" => OrganizationConstants::STATUS_CANCELLED]);
        }

        return ["cancelled" => $orgs->count()];
    }

    /**
     * Purges organizations whose 30-day deletion grace period has elapsed.
     * Every membership (admin included — the whole company is going away) is
     * deactivated; member USER accounts are left untouched, since a person's
     * own TalkAM history shouldn't disappear because their employer's did.
     * The organization row itself is soft-deleted, not destroyed outright.
     */
    public static function purgeScheduledDeletions(): array
    {
        $orgs = Organization::whereNotNull("scheduled_deletion_at")
            ->where("scheduled_deletion_at", "<=", now())
            ->get();

        foreach ($orgs as $org) {
            $org->members()->update([
                "status" => OrganizationConstants::MEMBER_INACTIVE,
                "deactivated_at" => now(),
            ]);

            $org->delete();
        }

        return ["purged" => $orgs->count()];
    }
}
