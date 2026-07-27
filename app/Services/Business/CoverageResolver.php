<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\User;

/**
 * Resolves how a booking is paid for (web §09).
 *
 * Feature-flagged: with `business.coverage_enabled` OFF, every booking is a
 * CONSUMER session — today's behaviour, untouched. With it on, an active
 * employee booking through a company that uses TalkAM's therapist network gets
 * one of the org-covered modes:
 *   - own therapist        → org_external (settled outside TalkAM)
 *   - postpay company      → org_meter    (metered, therapist paid after settlement)
 *   - prepay, bundle left  → org_bundle   (drawn from the bundle, paid on completion)
 *   - prepay, bundle empty → the org's bundle_exhausted_policy (auto_meter | blocked)
 *
 * Pure: it reads state and returns a decision. The booking branch (9.1b-live)
 * acts on it (draw the bundle, skip the charge, or reject a blocked booking).
 */
class CoverageResolver
{
    public static function resolve(User $client, Therapist $therapist): array
    {
        if (!config('business.coverage_enabled')) {
            return self::consumer();
        }

        $org = OrganizationMember::where('user_id', $client->id)
            ->where('role', OrganizationConstants::ROLE_EMPLOYEE)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->with('organization')
            ->first()?->organization;

        // Not an active employee, or the company doesn't use TalkAM's network.
        if (!$org || !$org->therapist_access) {
            return self::consumer();
        }

        // The company's OWN therapist → settled outside TalkAM.
        if (self::isOwnTherapist($org, $therapist)) {
            return self::decision(Cov::ORG_EXTERNAL, $org->id, 0, Cov::PAYOUT_EXTERNAL);
        }

        // Postpay company → always metered.
        if ($org->payment_timing === 'postpay') {
            return self::metered($org);
        }

        // Prepay company → draw the bundle while it lasts.
        if (BundleLedgerService::remaining($org) > 0) {
            return self::decision(
                Cov::ORG_BUNDLE,
                $org->id,
                (int) config('business.session_rate'),
                Cov::PAYOUT_ON_COMPLETION
            );
        }

        // Prepay bundle exhausted → follow the org admin's policy.
        return match ($org->bundle_exhausted_policy) {
            'auto_meter' => self::metered($org),
            'block' => self::blocked($org, "Your company's session bundle is used up. Please contact your HR admin."),
            default => self::blocked($org, "Your company's session bundle is used up — your HR admin needs to top it up before you can book."),
        };
    }

    private static function isOwnTherapist(Organization $org, Therapist $therapist): bool
    {
        return OrganizationMember::where('organization_id', $org->id)
            ->where('user_id', $therapist->user_id)
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->exists();
    }

    private static function metered(Organization $org): array
    {
        return self::decision(
            Cov::ORG_METER,
            $org->id,
            (int) config('business.session_custom_rate'),
            Cov::PAYOUT_ON_SETTLEMENT
        );
    }

    private static function consumer(): array
    {
        return self::decision(Cov::CONSUMER, null, 0, Cov::PAYOUT_ON_COMPLETION);
    }

    private static function blocked(Organization $org, string $reason): array
    {
        return [
            'coverage' => Cov::BLOCKED,
            'organization_id' => $org->id,
            'billed_amount' => 0,
            'payout_timing' => null,
            'blocked_reason' => $reason,
        ];
    }

    private static function decision(string $coverage, ?int $org_id, int $billed, string $payout): array
    {
        return [
            'coverage' => $coverage,
            'organization_id' => $org_id,
            'billed_amount' => $billed,
            'payout_timing' => $payout,
            'blocked_reason' => null,
        ];
    }
}
