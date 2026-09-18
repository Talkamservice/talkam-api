<?php

namespace Tests\Feature\V2\Earnings;

use App\Models\TherapistWalletTransaction;
use App\Models\TherapySession;
use App\Services\Therapist\EarningsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_completion_creates_exactly_one_credit(): void
    {
        $session = TherapySession::factory()->create([
            "amount" => 25000,
            "status" => "in_progress",
            "starts_at" => now()->subHours(2),
            "started_at" => now()->subHours(2),
            "client_joined_at" => now()->subHours(2),
            "therapist_joined_at" => now()->subHours(2),
        ]);

        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $share = config("therapist.platform_share_percent");
        $this->assertDatabaseHas("therapist_wallet_transactions", [
            "therapist_id" => $session->therapist_id,
            "session_id" => $session->id,
            "type" => "credit",
            "amount" => round(25000 * (1 - $share / 100), 2),
        ]);
        $this->assertSame(1, TherapistWalletTransaction::count());
    }

    public function test_double_completion_does_not_double_credit(): void
    {
        $session = TherapySession::factory()->completed()->create();

        EarningsLedgerService::creditForSession($session);
        EarningsLedgerService::creditForSession($session);
        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $this->assertSame(1, TherapistWalletTransaction::where("session_id", $session->id)->count());
    }

    public function test_cancelled_session_creates_no_credit(): void
    {
        $session = TherapySession::factory()->create(["status" => "cancelled"]);

        EarningsLedgerService::creditForSession($session);
        $this->artisan("bookings:sweep-session-completions")->assertSuccessful();

        $this->assertSame(0, TherapistWalletTransaction::count());
    }
}
