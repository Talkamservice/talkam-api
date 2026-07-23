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

    public function test_missing_or_invalid_date_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$therapist] = $this->therapistWithMondayAvailability();

        $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots")->assertStatus(422);
        $this->getJson("/api/v2/user/therapists/{$therapist->id}/slots?date=not-a-date")->assertStatus(422);
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
