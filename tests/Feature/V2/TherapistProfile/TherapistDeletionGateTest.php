<?php

namespace Tests\Feature\V2\TherapistProfile;

use App\Models\Therapist;
use App\Models\TherapistReview;
use App\Models\TherapistWalletTransaction;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistDeletionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_blocked_when_future_confirmed_bookings_exist(): void
    {
        $therapist = Therapist::factory()->create();
        TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "starts_at" => now()->addDays(2),
        ]);
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/user/profile/delete-account")
            ->assertStatus(400)->assertJson(["success" => false]);

        $this->assertDatabaseHas("therapists", ["id" => $therapist->id, "status" => "Active"]);
        $this->assertDatabaseHas("users", ["id" => $therapist->user_id]);
    }

    public function test_delete_blocked_when_wallet_balance_unwithdrawn(): void
    {
        $therapist = Therapist::factory()->create();
        TherapistWalletTransaction::create([
            "therapist_id" => $therapist->id,
            "type" => "credit",
            "amount" => 20000,
        ]);
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/user/profile/delete-account")
            ->assertStatus(400)->assertJson(["success" => false]);

        $this->assertDatabaseHas("users", ["id" => $therapist->user_id]);
    }

    public function test_delete_proceeds_after_refunds_and_payout(): void
    {
        $therapist = Therapist::factory()->create();
        $user_id = $therapist->user_id;
        // A past completed session with a retained review.
        $session = TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id]);
        TherapistReview::factory()->create([
            "session_id" => $session->id,
            "therapist_id" => $therapist->id,
            "rating" => 5,
        ]);

        Sanctum::actingAs($therapist->user);
        $this->postJson("/api/v2/user/profile/delete-account")->assertStatus(200);

        // User gone; therapist row survives (detached + Deleted) so history
        // and anonymised reviews are retained; directory drops them.
        $this->assertDatabaseMissing("users", ["id" => $user_id]);
        $this->assertDatabaseHas("therapists", ["id" => $therapist->id, "status" => "Deleted", "user_id" => null]);
        $this->assertSame(1, TherapistReview::where("therapist_id", $therapist->id)->count());

        $viewer = Therapist::factory()->create();
        Sanctum::actingAs($viewer->user);
        $ids = collect($this->getJson("/api/v2/user/therapists")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($therapist->id));
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson("/api/v2/user/profile/delete-account")->assertStatus(401);
    }
}
