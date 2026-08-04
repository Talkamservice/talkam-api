<?php

namespace App\Services\Business;

use App\Models\Organization;
use App\Models\Therapist;

/**
 * Every price the B2B onboarding screens render is computed here, server-side.
 * The browser never decides a figure — it only displays what this returns.
 *
 * Model (web §08 billing redesign):
 *   seats_monthly = seats × seat_rate           (seat_rate = the volume TIER price;
 *                                                 the facilitation fee, billed monthly)
 *   sessions — only when the org uses the TalkAM therapist network:
 *     · prepay   → a bundle bought upfront: bundle_sessions × session rate
 *                  (block ₦8,000, or the +3% custom rate for a non-block quantity)
 *     · postpay  → pay-as-you-go: no bundle; sessions metered and invoiced at
 *                  month-end at the custom (no-commitment) rate.
 *
 * The old "Therapist Network Access" per-seat fee and the "blended/fairness"
 * plan figure were removed here — the plan card is now a faithful summary of the
 * selection (per_seat = the tier price, full stop).
 */
class OrganizationPricingService
{
    /** The volume tier a seat count falls into. */
    public static function tier(int $seats): array
    {
        $tiers = config("business.seat_tiers");

        foreach ($tiers as $tier) {
            $above_min = $seats >= $tier["min"];
            $below_max = $tier["max"] === null || $seats <= $tier["max"];

            if ($above_min && $below_max) {
                return $tier;
            }
        }

        // Seats below the first tier's minimum (0 or negative) price at the
        // entry tier; the caller validates seats >= 1 before ever getting here.
        return $tiers[0];
    }

    /**
     * Live mean session rate across the bench, falling back to the configured
     * value when nobody is on it yet. Retained for the public pricing page's
     * worked example; the onboarding quote no longer blends it into the seat price.
     */
    public static function networkAverageRate(): float
    {
        $average = Therapist::status()
            ->whereNotNull("session_rate")
            ->avg("session_rate");

        return (float) ($average ?: config("business.network_average_rate"));
    }

    /** Verified therapists already serving companies — the bench-screen blurb. */
    public static function benchTherapistCount(): int
    {
        return Therapist::status()->whereNotNull("verified_at")->count();
    }

    /**
     * Full quote for a seat count / network / session combination. Pure — takes
     * no model, so the seats screen can price a choice before it is persisted.
     */
    public static function quote(
        int $seats,
        bool $uses_network,
        int $bundle_sessions,
        bool $bundle_custom = false,
        string $timing = "prepay"
    ): array {
        $block_rate = (int) config("business.session_rate");
        $custom_rate = (int) config("business.session_custom_rate");

        $seats = max($seats, 0);
        $timing = in_array($timing, config("business.payment_timings"), true) ? $timing : "prepay";
        $prepay = $timing === "prepay";

        // Sessions only exist when the org uses the TalkAM network. Prepay commits
        // a bundle now; postpay meters as-you-go, so there is no bundle to price.
        $has_bundle = $uses_network && $prepay;
        $bundle_sessions = $has_bundle ? max($bundle_sessions, 0) : 0;
        $bundle_custom = $has_bundle ? $bundle_custom : false;

        $session_rate = $bundle_custom ? $custom_rate : $block_rate;
        $metered_active = $uses_network && !$prepay;

        $tier = self::tier($seats);
        $seat_rate = (int) $tier["price"];

        $seats_monthly = $seats * $seat_rate;
        $bundle_total = $bundle_sessions * $session_rate;

        return [
            "currency" => config("business.currency"),
            "seats" => $seats,
            "uses_network" => $uses_network,
            "payment_timing" => $timing,
            "bundle_custom" => (bool) $bundle_custom,
            "bundle_sessions" => $bundle_sessions,
            "metered_sessions" => $metered_active,
            "rates" => [
                "seat" => $seat_rate,
                "session_block" => $block_rate,
                "session_custom" => $custom_rate,
                "session_applied" => $session_rate,   // rate the prepaid bundle used
                "metered_session" => $custom_rate,     // pay-as-you-go rate
            ],
            "tier" => $tier,
            "seats_monthly" => $seats_monthly,
            "bundle_total" => $bundle_total,
            // What is owed up front (prepay bundle) vs on the recurring invoice.
            "due_now" => $prepay ? $bundle_total : 0,
            "billed_monthly" => $seats_monthly,
            // Recurring monthly total. The bundle is a one-off, so it is NOT here.
            "total_monthly" => $seats_monthly,
            "plan" => self::planShape($seat_rate),
        ];
    }

    /** Quote for an organization's currently saved choices. */
    public static function quoteFor(Organization $organization): array
    {
        return self::quote(
            (int) $organization->seats_licensed,
            (bool) $organization->therapist_access,
            (int) $organization->session_bundle_sessions,
            (bool) $organization->bundle_custom,
            $organization->payment_timing ?? "prepay"
        );
    }

    /**
     * The single Phase-1 plan the Step-4 card renders. per_seat is simply the
     * seat tier rate — no fairness multiplier — so the card matches the seats
     * screen exactly.
     */
    private static function planShape(int $seat_rate): array
    {
        return [
            "key" => "lite",
            "name" => config("business.plan.name"),
            "per_seat" => $seat_rate,
            "features" => config("business.plan.features"),
        ];
    }

    /**
     * The static half of the pricing contract — everything the seats and plan
     * screens render as copy rather than as a computed figure.
     *
     * Existing rate keys are retained for the public pricing page, which still
     * renders the older three-layer deck; §08 adds the session-custom rate and
     * the prepay/postpay timings the onboarding screens now use.
     */
    public static function config(): array
    {
        return [
            "currency" => config("business.currency"),
            "seat_tiers" => config("business.seat_tiers"),
            "employee_seat_rate" => (int) config("business.employee_seat_rate"),
            "therapist_access_rate" => (int) config("business.therapist_access_rate"),
            "session_rate" => (int) config("business.session_rate"),
            "session_custom_rate" => (int) config("business.session_custom_rate"),
            "standard_therapist_rate" => (float) config("business.standard_therapist_rate"),
            "network_average_rate" => round(self::networkAverageRate(), 2),
            "bundle_options" => config("business.bundle_options"),
            "headcount_bands" => config("business.headcount_bands"),
            "payment_timings" => config("business.payment_timings"),
            "plan" => config("business.plan"),
            "pay_methods" => config("business.pay_methods"),
            "bank_details" => config("business.bank_details"),
            "dpo_email" => config("business.dpo_email"),
            "aggregate_minimum_cohort" => (int) config("business.aggregate_minimum_cohort"),
        ];
    }
}
