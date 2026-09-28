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

class SlotEngineTest extends TestCase
{
    use RefreshDatabase;

    private function therapistWithMondayAvailability(): array
    {
        $therapist = Therapist::factory()->create(); // 50 min + 10 buffer
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "monday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);
        $monday = Carbon::parse("next monday")->toDateString();

        return [$therapist, $monday];
    }

    public function test_slots_derive_from_availability_duration_and_buffer(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $monday] = $this->therapistWithMondayAvailability();

        // 09:00-12:00 window, 50 min sessions, 10 min buffer → 09:00, 10:00, 11:00.
        $slots = $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$monday")
            ->assertStatus(200)
            ->json("data.slots");

        $this->assertEquals(
            ["$monday 09:00:00", "$monday 10:00:00", "$monday 11:00:00"],
            collect($slots)->pluck("starts_at")->all()
        );
    }

    public function test_confirmed_booking_excludes_slot(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "starts_at" => "$monday 10:00:00",
        ]);

        $starts = collect($this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$monday")->json("data.slots"))
            ->pluck("starts_at");
        $this->assertFalse($starts->contains("$monday 10:00:00"));
        $this->assertTrue($starts->contains("$monday 09:00:00"));
    }

    public function test_active_hold_excludes_slot(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        TherapySession::factory()->pending()->create([
            "therapist_id" => $therapist->id,
            "starts_at" => "$monday 10:00:00",
        ]);

        $starts = collect($this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$monday")->json("data.slots"))
            ->pluck("starts_at");
        $this->assertFalse($starts->contains("$monday 10:00:00"));
    }

    public function test_expired_hold_frees_slot(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        TherapySession::factory()->pending()->create([
            "therapist_id" => $therapist->id,
            "starts_at" => "$monday 10:00:00",
            "hold_expires_at" => now()->subMinute(),
        ]);

        $starts = collect($this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$monday")->json("data.slots"))
            ->pluck("starts_at");
        $this->assertTrue($starts->contains("$monday 10:00:00"));
    }

    public function test_all_availability_blocks_for_a_day_produce_slots(): void
    {
        // The grid stores one row per bookable block, so a day can hold several
        // windows. Every active block must be offered — not just the first.
        Sanctum::actingAs(User::factory()->create());
        $therapist = Therapist::factory()->create();
        foreach (["09:00" => "09:50", "11:00" => "11:50", "15:00" => "15:50"] as $start => $end) {
            TherapistAvailability::factory()->create([
                "user_id" => $therapist->user_id,
                "day_of_week" => "monday",
                "start_time" => $start,
                "end_time" => $end,
            ]);
        }
        $monday = Carbon::parse("next monday")->toDateString();

        $starts = collect($this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$monday")->json("data.slots"))
            ->pluck("starts_at")->all();

        // A later block (15:00) is reachable even though 09:00 comes first.
        $this->assertEquals(
            ["$monday 09:00:00", "$monday 11:00:00", "$monday 15:00:00"],
            $starts
        );
    }

    public function test_slots_match_availability_saved_through_the_real_write_path(): void
    {
        // The bug the block-by-block test above targets slipped past the suite
        // because fixtures hand-built ONE wide window, a shape the availability
        // screen never writes. Save the grid the way the app actually does —
        // through replace(), which stores one row per block — so the read path
        // is exercised against realistic data and can't silently diverge again.
        Sanctum::actingAs(User::factory()->create());
        $therapist = Therapist::factory()->create();
        (new \App\Services\Therapist\TherapistAvailabilityService)->replace($therapist->user, [
            "days" => [
                "mon" => [
                    ["start" => "09:00", "end" => "09:50", "active" => true],
                    ["start" => "11:00", "end" => "11:50", "active" => true],
                    ["start" => "15:00", "end" => "15:50", "active" => true],
                ],
            ],
        ]);
        $monday = Carbon::parse("next monday")->toDateString();

        $starts = collect($this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$monday")->json("data.slots"))
            ->pluck("starts_at")->all();

        $this->assertEquals(
            ["$monday 09:00:00", "$monday 11:00:00", "$monday 15:00:00"],
            $starts
        );
    }

    public function test_invalid_date_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist] = $this->therapistWithMondayAvailability();

        $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=not-a-date")->assertStatus(422);
    }

    /** No date = the booking/reschedule pickers' "what's soonest" mode —
     *  scans forward instead of requiring the caller to name a bookable day. */
    public function test_missing_date_scans_forward_for_next_available_slots(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist, $monday] = $this->therapistWithMondayAvailability();

        $response = $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots")
            ->assertStatus(200);

        $this->assertNull($response->json("data.date"));
        $starts = collect($response->json("data.slots"))->pluck("starts_at");
        $this->assertNotEmpty($starts);
        // Only Monday has availability, so with 3 slots/week the scan keeps
        // going past this Monday into the next one before hitting the cap —
        // the soonest result is what matters, not that every slot shares a day.
        $this->assertTrue(str_starts_with($starts->first(), $monday));
    }

    public function test_missing_date_caps_at_six_slots(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $therapist = Therapist::factory()->create();
        // Two 3-hour windows on consecutive available days — well over 6
        // possible slots between them, so this proves the cap actually bites.
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "monday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "tuesday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);

        $slots = $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots")
            ->assertStatus(200)
            ->json("data.slots");

        $this->assertCount(6, $slots);
    }

    public function test_inactive_day_returns_empty_list(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist] = $this->therapistWithMondayAvailability();
        $tuesday = Carbon::parse("next tuesday")->toDateString();

        $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=$tuesday")
            ->assertStatus(200)
            ->assertJsonPath("data.slots", []);
    }
}
