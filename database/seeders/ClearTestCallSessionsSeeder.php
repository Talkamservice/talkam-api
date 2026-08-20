<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\TherapistWalletTransaction;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClearTestCallSessionsSeeder extends Seeder
{
    /**
     * Wipes only the sessions BETWEEN this specific therapist/client pair
     * (e.g. everything CreateTestCallSessionSeeder created) — unlike
     * ClearUserSessionsSeeder, this leaves the therapist's real sessions
     * with other clients (e.g. "Paul Michael2") untouched.
     */
    private const THERAPIST_EMAIL = 'mikebingpseventh@gmail.com';
    private const CLIENT_EMAIL = 'call-test-client@talkam.test';

    public function run(): void
    {
        $this->command->info(
            "🧹 Clearing sessions between " . self::THERAPIST_EMAIL . " and " . self::CLIENT_EMAIL . "..."
        );

        $therapistUser = User::where('email', self::THERAPIST_EMAIL)->first();
        $client = User::where('email', self::CLIENT_EMAIL)->first();

        if (! $therapistUser || ! $therapistUser->therapist) {
            $this->command->warn("⚠️  No therapist found for " . self::THERAPIST_EMAIL);
            return;
        }
        if (! $client) {
            $this->command->warn("⚠️  No client found for " . self::CLIENT_EMAIL);
            return;
        }

        $sessionIds = TherapySession::where('therapist_id', $therapistUser->therapist->id)
            ->where('user_id', $client->id)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            $this->command->info("✅ No sessions found between these two — nothing to clear.");
            return;
        }

        $this->command->info("Found {$sessionIds->count()} session(s) to remove.");

        $paymentIds = TherapySession::whereIn('id', $sessionIds)
            ->whereNotNull('payment_id')
            ->pluck('payment_id')
            ->unique();

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

        $this->command->info("✅ Done — no sessions remain between these two accounts.");
    }
}
