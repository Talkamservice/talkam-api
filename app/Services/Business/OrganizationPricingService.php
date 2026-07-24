<?php

namespace App\Services\Business;

use App\Models\Organization;
use App\Models\Therapist;

/**
 * Every price the B2B onboarding screens render is computed here, server-side.
 * The browser never decides a figure — it only displays what this returns.
 *
 * Model (deck "TalkAM B2B Auth.dc.html"):
 *   monthly total = seats x employee_seat_rate
 *                 + seats x therapist_access_rate   (when therapist access on)
 *                 + bundle_sessions x session_rate  (when therapist access on)
 *
 * The plan card shows a second, "blended" figure: the seat-count tier rate
 * multiplied by a fairness adjustment (network average therapist rate over the
 * standard rate), so pricing tracks the real bench rather than a fixed number.
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
     * value when nobody is on it yet. "On the bench" is the same scope the §07
     * directory uses (Therapist::status()), so the price tracks exactly the
     * therapists an employee could actually book.
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
     * Full quote for a seat count / therapist-access / bundle combination.
     * Pure — takes no model, so the seats screen can price a choice before it
     * is persisted.
     */
    public static function quote(int $seats, bool $therapist_access, int $bundle_sessions): array
    {
        $employee_rate = (int) config("business.employee_seat_rate");
        $therapist_rate = (int) config("business.therapist_access_rate");
        $session_rate = (int) config("business.session_rate");
        $standard_rate = (float) config("business.standard_therapist_rate");

        $seats = max($seats, 0);
        $bundle_sessions = $therapist_access ? max($bundle_sessions, 0) : 0;

        $employee_seats_monthly = $seats * $employee_rate;
        $therapist_access_monthly = $therapist_access ? $seats * $therapist_rate : 0;
        $session_bundle_monthly = $bundle_sessions * $session_rate;

        $tier = self::tier($seats);
        $network_average = self::networkAverageRate();
        $fairness = $standard_rate > 0 ? $network_average / $standard_rate : 1.0;
        $per_seat = $tier["price"] * $fairness;

        return [
            "currency" => config("business.currency"),
            "seats" => $seats,
            "therapist_access" => $therapist_access,
            "bundle_sessions" => $bundle_sessions,
            "rates" => [
                "employee_seat" => $employee_rate,
                "therapist_access" => $therapist_rate,
                "session" => $session_rate,
            ],
            "tier" => $tier,
            "employee_seats_monthly" => $employee_seats_monthly,
            "therapist_access_monthly" => $therapist_access_monthly,
            "session_bundle_monthly" => $session_bundle_monthly,
            "total_monthly" => $employee_seats_monthly + $therapist_access_monthly + $session_bundle_monthly,
            "blended" => [
                "standard_rate" => $standard_rate,
                "network_average_rate" => round($network_average, 2),
                "fairness_multiplier" => round($fairness, 2),
                "per_seat" => round($per_seat, 2),
                "total_monthly" => round($per_seat * $seats, 2),
            ],
        ];
    }

    /** Quote for an organization's currently saved choices. */
    public static function quoteFor(Organization $organization): array
    {
        return self::quote(
            (int) $organization->seats_licensed,
            (bool) $organization->therapist_access,
            (int) $organization->session_bundle_sessions
        );
    }

    /**
     * The static half of the pricing contract — everything the seats and plan
     * screens render as copy rather than as a computed figure.
     */
    public static function config(): array
    {
        return [
            "currency" => config("business.currency"),
            "seat_tiers" => config("business.seat_tiers"),
            "employee_seat_rate" => (int) config("business.employee_seat_rate"),
            "therapist_access_rate" => (int) config("business.therapist_access_rate"),
            "session_rate" => (int) config("business.session_rate"),
            "standard_therapist_rate" => (float) config("business.standard_therapist_rate"),
            "network_average_rate" => round(self::networkAverageRate(), 2),
            "bundle_options" => config("business.bundle_options"),
            "plan" => config("business.plan"),
            "pay_methods" => config("business.pay_methods"),
            "bank_details" => config("business.bank_details"),
        ];
    }
}
