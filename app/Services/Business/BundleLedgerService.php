<?php

namespace App\Services\Business;

use App\Models\Organization;
use App\Models\OrganizationBundleEntry;
use App\Models\TherapySession;
use Illuminate\Support\Facades\DB;

/**
 * The prepaid session-bundle ledger (web §09 Phase 9.1a).
 *
 * Pure accounting: records draws and refunds against a company's bundle and
 * derives the remaining balance. It does NOT decide whether a draw is allowed —
 * the booking branch (9.1b) checks `remaining()` against the org's
 * `bundle_exhausted_policy` before calling `draw()`. Kept separate so the ledger
 * stays a dumb, auditable, idempotent counter.
 *
 *   remaining = session_bundle_sessions (purchased) − session_bundle_used
 *
 * organization_bundle_entries is the audit trail. Draw/refund are idempotent per
 * session, so a retried booking or a double cancellation never corrupts the count.
 */
class BundleLedgerService
{
    const REASON_PURCHASE = "purchase";
    const REASON_DRAW = "draw";
    const REASON_REFUND = "refund";

    public static function remaining(Organization $organization): int
    {
        // Prepay activates on payment (web §08/§11): an unfunded bundle is not yet
        // usable, even though the purchased count is already recorded.
        if (empty($organization->session_bundle_funded_at)) {
            return 0;
        }

        return max(0, (int) $organization->session_bundle_sessions - (int) $organization->session_bundle_used);
    }

    /** Draw one session from the bundle for a booking. Idempotent per session. */
    public static function draw(Organization $organization, TherapySession $session): OrganizationBundleEntry
    {
        return DB::transaction(function () use ($organization, $session) {
            $existing = OrganizationBundleEntry::where("therapy_session_id", $session->id)
                ->where("reason", self::REASON_DRAW)
                ->first();

            if ($existing) {
                return $existing;
            }

            $organization->increment("session_bundle_used");

            return OrganizationBundleEntry::create([
                "organization_id" => $organization->id,
                "therapy_session_id" => $session->id,
                "delta" => -1,
                "reason" => self::REASON_DRAW,
            ]);
        });
    }

    /**
     * Return a drawn session to the bundle (cancellation / no-show). Idempotent:
     * a no-op when the session was never drawn or has already been refunded.
     */
    public static function refund(Organization $organization, TherapySession $session): ?OrganizationBundleEntry
    {
        return DB::transaction(function () use ($organization, $session) {
            $drawn = OrganizationBundleEntry::where("therapy_session_id", $session->id)
                ->where("reason", self::REASON_DRAW)
                ->exists();

            $already_refunded = OrganizationBundleEntry::where("therapy_session_id", $session->id)
                ->where("reason", self::REASON_REFUND)
                ->exists();

            if (!$drawn || $already_refunded) {
                return null;
            }

            if ((int) $organization->session_bundle_used > 0) {
                $organization->decrement("session_bundle_used");
            }

            return OrganizationBundleEntry::create([
                "organization_id" => $organization->id,
                "therapy_session_id" => $session->id,
                "delta" => 1,
                "reason" => self::REASON_REFUND,
            ]);
        });
    }
}
