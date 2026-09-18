<?php

namespace Tests\Feature\V2\Booking;

use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    private function bookableSlot(): array
    {
        $therapist = Therapist::factory()->create(["session_rate" => 17500]);
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "monday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);
        $starts_at = Carbon::parse("next monday")->setTime(10, 0)->toDateTimeString();

        return [$therapist, $starts_at];
    }

    public function test_valid_slot_creates_pending_booking_with_hold(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$therapist, $starts_at] = $this->bookableSlot();

        $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $therapist->id,
            "starts_at" => $starts_at,
            "format" => "video",
            "notes" => "I'm a workaholic",
        ])->assertStatus(200)->assertJsonPath("data.status", "pending_payment");

        $session = TherapySession::first();
        $this->assertNotNull($session->hold_expires_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes(config("therapist.booking.hold_minutes"))->timestamp,
            $session->hold_expires_at->timestamp,
            5
        );
    }

    public function test_amount_computed_from_stored_rate_ignoring_client_amount(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $starts_at] = $this->bookableSlot();

        $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $therapist->id,
            "starts_at" => $starts_at,
            "format" => "video",
            "amount" => 1,
        ])->assertStatus(200);

        $this->assertEquals(17500, (float) TherapySession::first()->amount);
    }

    public function test_therapist_without_a_rate_is_rejected_cleanly_not_a_500(): void
    {
        // A business-provisioned therapist is created bare (just a user_id via
        // firstOrCreate on invite-accept), so session_rate is null. Booking one
        // must fail with a clean 422 — never a NOT NULL crash leaking SQL, which
        // is what a rate-less verified therapist produced in the wild.
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $starts_at] = $this->bookableSlot();
        $therapist->update(["session_rate" => null]);

        $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $therapist->id,
            "starts_at" => $starts_at,
            "format" => "video",
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, TherapySession::count());
    }

    public function test_missing_required_fields_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist] = $this->bookableSlot();

        $this->postJson("/api/v2/user/bookings", ["therapist_id" => $therapist->id])
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, TherapySession::count());
    }

    public function test_format_not_offered_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $starts_at] = $this->bookableSlot();
        $therapist->update(["session_formats" => ["video"]]);

        $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $therapist->id,
            "starts_at" => $starts_at,
            "format" => "voice",
        ])->assertStatus(422);

        $this->assertSame(0, TherapySession::count());
    }

    public function test_unbookable_slot_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist] = $this->bookableSlot();
        $off_slot = Carbon::parse("next monday")->setTime(20, 0)->toDateTimeString();

        $this->postJson("/api/v2/user/bookings", [
            "therapist_id" => $therapist->id,
            "starts_at" => $off_slot,
            "format" => "video",
        ])->assertStatus(422);

        $this->assertSame(0, TherapySession::count());
    }

    public function test_double_booking_same_slot_rejected(): void
    {
        [$therapist, $starts_at] = $this->bookableSlot();
        $payload = [
            "therapist_id" => $therapist->id,
            "starts_at" => $starts_at,
            "format" => "video",
        ];

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/user/bookings", $payload)->assertStatus(200);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/user/bookings", $payload)->assertStatus(422);

        $this->assertSame(1, TherapySession::count());
    }

    public function test_slot_rebookable_after_hold_expiry_and_cleanup(): void
    {
        [$therapist, $starts_at] = $this->bookableSlot();
        $payload = [
            "therapist_id" => $therapist->id,
            "starts_at" => $starts_at,
            "format" => "video",
        ];

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/user/bookings", $payload)->assertStatus(200);

        $this->travel(config("therapist.booking.hold_minutes") + 1)->minutes();
        $this->artisan("bookings:release-expired-holds")->assertSuccessful();
        $this->assertDatabaseHas("therapy_sessions", ["status" => "expired"]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/user/bookings", $payload)->assertStatus(200);

        $this->assertSame(2, TherapySession::count());
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/bookings", [])->assertStatus(401);
    }
}
