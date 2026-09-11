<?php

namespace Tests\Feature\V2\Earnings;

use App\Models\Payout;
use App\Models\Therapist;
use App\Models\TherapistPayoutAccount;
use App\Models\TherapistWalletTransaction;
use App\Notifications\Therapist\PayoutReceivedNotification;
use App\Services\Therapist\EarningsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayoutWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function pendingPayout(): Payout
    {
        $therapist = Therapist::factory()->create();
        $account = TherapistPayoutAccount::create([
            "user_id" => $therapist->user_id,
            "bank_code" => "058",
            "bank_name" => "First Bank",
            "account_number" => "0123455678",
            "account_name" => "ADA OBI",
            "verified_at" => now(),
        ]);
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id,
            "type" => "credit",
            "amount" => 80000,
        ]);
        $payout = Payout::create([
            "therapist_id" => $therapist->id,
            "payout_account_id" => $account->id,
            "amount" => 80000,
            "provider_ref" => "PAYOUT-TEST123",
            "status" => "pending",
        ]);
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id,
            "type" => "debit",
            "payout_id" => $payout->id,
            "amount" => 80000,
            "reference" => "PAYOUT-TEST123",
        ]);

        return $payout;
    }

    public function test_success_event_marks_payout_successful_and_fires_notification(): void
    {
        Notification::fake();
        $payout = $this->pendingPayout();

        $this->postJson("/api/v1/webhook/verifications", [
            "event" => "transfer.completed",
            "data" => ["reference" => "PAYOUT-TEST123", "status" => "SUCCESSFUL"],
        ])->assertStatus(200);

        $payout->refresh();
        $this->assertSame("successful", $payout->status);
        $this->assertNotNull($payout->completed_at);
        Notification::assertSentTo($payout->therapist->user, PayoutReceivedNotification::class);
        // Debit stands — balance stays 0.
        $this->assertEquals(0, EarningsLedgerService::balance($payout->therapist));
    }

    public function test_failure_event_reverses_debit_and_marks_payout_failed(): void
    {
        Notification::fake();
        $payout = $this->pendingPayout();

        $this->postJson("/api/v1/webhook/verifications", [
            "event" => "transfer.completed",
            "data" => ["reference" => "PAYOUT-TEST123", "status" => "FAILED"],
        ])->assertStatus(200);

        $this->assertSame("failed", $payout->refresh()->status);
        // Debit reversed — balance restored to its pre-payout value.
        $this->assertEquals(80000, EarningsLedgerService::balance($payout->therapist));
        Notification::assertNothingSent();
    }

    public function test_transfer_event_is_hook_logged(): void
    {
        $this->pendingPayout();

        $this->postJson("/api/v1/webhook/verifications", [
            "event" => "transfer.completed",
            "data" => ["reference" => "PAYOUT-TEST123", "status" => "SUCCESSFUL"],
        ]);

        $this->assertDatabaseHas("hook_logs", ["event" => "transfer.completed"]);
    }

    public function test_non_transfer_events_still_reach_v1_handler(): void
    {
        $payout = $this->pendingPayout();

        // A v1 event is accepted, hook-logged, and never touches payouts.
        $this->postJson("/api/v1/webhook/verifications", [
            "event" => "subscription.cancelled",
            "data" => ["id" => 1],
        ])->assertStatus(200);

        $this->assertDatabaseHas("hook_logs", ["event" => "subscription.cancelled"]);
        $this->assertSame("pending", $payout->refresh()->status);
    }
}
