<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\TherapistWalletTransaction;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClearUserSessionsSeeder extends Seeder
{
    /**
     * Wipes every therapy session belonging to this user, whether they were
     * the client or the therapist on it (e.g. undoing TherapistEarningsSeeder
     * for mikebingpseventh@gmail.com).
     */
    private const TARGET_EMAIL = 'mikebingpseventh@gmail.com';

    public function run(): void
    {
        $this->command->info("🧹 Clearing sessions for " . self::TARGET_EMAIL . "...");

        $user = User::where('email', self::TARGET_EMAIL)->first();

        if (! $user) {
            $this->command->warn("⚠️  No user found with email " . self::TARGET_EMAIL);
            return;
        }

        $sessionIds = TherapySession::where('user_id', $user->id)->pluck('id');

        $therapist = $user->therapist;
        if ($therapist) {
            $sessionIds = $sessionIds->merge(
                TherapySession::where('therapist_id', $therapist->id)->pluck('id')
            );
        }

        $sessionIds = $sessionIds->unique()->values();

        if ($sessionIds->isEmpty()) {
            $this->command->info("✅ No sessions found — nothing to clear.");
            return;
        }

        $this->command->info("Found {$sessionIds->count()} session(s) to remove.");

        // Payments created to back these sessions. Deleting a session does
        // not cascade to its payment (payments intentionally outlive
        // sessions), so collect them up front and remove them separately —
        // but only if no other session still references them.
        $paymentIds = TherapySession::whereIn('id', $sessionIds)
            ->whereNotNull('payment_id')
            ->pluck('payment_id')
            ->unique();

        // Wallet transactions pointing at these sessions are nulled (not
        // deleted) by the FK, which would leave dangling "earning" ledger
        // rows with no session behind them — remove them explicitly.
        $walletTxnCount = TherapistWalletTransaction::whereIn('session_id', $sessionIds)->delete();
        $this->command->info("🗑️  Deleted {$walletTxnCount} wallet transaction(s) tied to these sessions.");

        // Deleting the sessions cascades to therapist_reviews,
        // session_reschedules and session_notes at the DB level.
        $deletedSessions = TherapySession::whereIn('id', $sessionIds)->delete();
        $this->command->info("🗑️  Deleted {$deletedSessions} session(s).");

        if ($paymentIds->isNotEmpty()) {
            $stillReferenced = TherapySession::whereIn('payment_id', $paymentIds)->pluck('payment_id');
            $paymentIdsToDelete = $paymentIds->diff($stillReferenced);

            if ($paymentIdsToDelete->isNotEmpty()) {
                $deletedPayments = Payment::whereIn('id', $paymentIdsToDelete)->delete();
                $this->command->info("🗑️  Deleted {$deletedPayments} payment(s) tied to those sessions.");
            }
        }

        $this->command->info("ℹ️  Payout / payout account records were left untouched — rerun a dedicated cleanup if you also want those cleared.");
        $this->command->info("✅ Done — " . $user->email . " has no remaining sessions.");
    }
}
