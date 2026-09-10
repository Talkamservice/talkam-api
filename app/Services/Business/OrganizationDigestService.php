<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\OrganizationDigest;
use App\Notifications\Business\OrganizationDigestNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The monthly usage-digest run (web §03 Settings → "Monthly usage digest").
 * One email per org per period, to admins subscribed via their own
 * `digest_summary` preference. Idempotent per (org, period) via
 * organization_digests, mirroring the billing run's invoice guard.
 */
class OrganizationDigestService
{
    public static function run(Carbon $period_start, Carbon $period_end): array
    {
        $orgs = Organization::where("status", OrganizationConstants::STATUS_ACTIVE)
            ->whereNotNull("verified_at")
            ->get();

        $sent = 0;

        foreach ($orgs as $org) {
            try {
                if (self::sendFor($org, $period_start, $period_end)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                logger("Monthly digest failed for org {$org->id}", ["error" => $e->getMessage()]);
            }
        }

        return ["orgs" => $orgs->count(), "sent" => $sent];
    }

    /** Returns true when a digest was actually sent (false when skipped or a repeat). */
    public static function sendFor(Organization $organization, Carbon $period_start, Carbon $period_end): bool
    {
        $member_ids = OrgAggregateService::memberIds($organization);
        $cohort = count($member_ids);

        // No point digesting a company with nobody on it yet.
        if ($cohort === 0) {
            return false;
        }

        $digest = OrganizationDigest::firstOrCreate(
            ["organization_id" => $organization->id, "period_start" => $period_start->toDateString()],
            ["period_end" => $period_end->toDateString()]
        );

        if ($digest->sent_at) {
            return false;
        }

        $recipients = AdminNotificationGateService::subscribedAdminsForOrg($organization, "digest_summary");

        if ($recipients->isNotEmpty()) {
            $summary = OrgAggregateService::monthlySummary($organization, $period_start, $period_end);

            // Additive here rather than in OrgAggregateService::monthlySummary()
            // itself, so that method's return shape (and the live dashboard's
            // unrelated overview() caller) is untouched.
            $active = $summary["active_members"];
            $engagement_rate = (!$active["suppressed"] && $cohort > 0)
                ? round(($active["value"] / $cohort) * 100, 1) . '%'
                : null;

            Notification::send($recipients, new OrganizationDigestNotification(
                $organization->name,
                $period_start->format("F Y"),
                $summary,
                [
                    "companyShortName" => Str::limit(Str::before($organization->name, ' '), 24, ''),
                    "engagementRate" => $engagement_rate,
                    "reportUrl" => rtrim((string) config("business.web_url"), "/") . "/business/admin/reports",
                ]
            ));
        }

        $digest->update(["sent_at" => now()]);

        return true;
    }
}
