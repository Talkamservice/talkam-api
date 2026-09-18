<?php

namespace Tests\Feature\V2\Sessions;

use App\Models\SessionReschedule;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionRescheduleNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private function sessionWithOpenSlots(): TherapySession
    {
        $therapist = Therapist::factory()->create();
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "monday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);

        return TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "starts_at" => Carbon::parse("next monday")->setTime(10, 0),
        ]);
    }

    public function test_valid_request_creates_pending_and_notifies_counterpart(): void
    {
        Notification::fake();
        $session = $this->sessionWithOpenSlots();
        Sanctum::actingAs($session->user);
        $new_slot = Carbon::parse("next monday")->setTime(11, 0)->toDateTimeString();

        $this->postJson("/api/v2/user/bookings/{$session->id}/reschedule", [
            "new_starts_at" => $new_slot,
            "reason" => "personal_emergency",
        ])->assertStatus(200)->assertJsonPath("data.status", "pending");

        $this->assertDatabaseHas("session_reschedules", [
            "session_id" => $session->id,
            "status" => "pending",
        ]);
        Notification::assertSentTo($session->therapist->user, SessionRescheduleNotification::class);
    }

    public function test_occupied_slot_rejected_by_slot_engine(): void
    {
        Notification::fake();
        $session = $this->sessionWithOpenSlots();
        // Another confirmed booking occupies 11:00.
        TherapySession::factory()->create([
            "therapist_id" => $session->therapist_id,
            "starts_at" => Carbon::parse("next monday")->setTime(11, 0),
        ]);
        Sanctum::actingAs($session->user);

        $this->postJson("/api/v2/user/bookings/{$session->id}/reschedule", [
            "new_starts_at" => Carbon::parse("next monday")->setTime(11, 0)->toDateTimeString(),
            "reason" => "personal_emergency",
        ])->assertStatus(422);

        $this->assertSame(0, SessionReschedule::count());
    }

    public function test_unlisted_reason_rejected(): void
    {
        $session = $this->sessionWithOpenSlots();
        Sanctum::actingAs($session->user);

        $this->postJson("/api/v2/user/bookings/{$session->id}/reschedule", [
            "new_starts_at" => Carbon::parse("next monday")->setTime(11, 0)->toDateTimeString(),
            "reason" => "just because",
        ])->assertStatus(422);

        $this->assertSame(0, SessionReschedule::count());
    }

    public function test_third_request_rejected_by_config_limit(): void
    {
        Notification::fake();
        $session = $this->sessionWithOpenSlots();
        SessionReschedule::factory()->count(2)->create([
            "session_id" => $session->id,
            "status" => "declined",
        ]);
        Sanctum::actingAs($session->user);

        $this->postJson("/api/v2/user/bookings/{$session->id}/reschedule", [
            "new_starts_at" => Carbon::parse("next monday")->setTime(11, 0)->toDateTimeString(),
            "reason" => "personal_emergency",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame(2, SessionReschedule::count());
    }

    public function test_request_inside_cutoff_rejected(): void
    {
        $session = $this->sessionWithOpenSlots();
        Sanctum::actingAs($session->user);

        // Travel to inside the config cutoff window before start.
        $cutoff = config("therapist.sessions.reschedule_cutoff_hours");
        $this->travelTo($session->starts_at->copy()->subHours($cutoff - 1));

        $this->postJson("/api/v2/user/bookings/{$session->id}/reschedule", [
            "new_starts_at" => $session->starts_at->copy()->addDays(7)->toDateTimeString(),
            "reason" => "personal_emergency",
        ])->assertStatus(400);

        $this->assertSame(0, SessionReschedule::count());
    }

    public function test_foreign_booking_rejected(): void
    {
        $session = $this->sessionWithOpenSlots();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/bookings/{$session->id}/reschedule", [
            "new_starts_at" => Carbon::parse("next monday")->setTime(11, 0)->toDateTimeString(),
            "reason" => "personal_emergency",
        ])->assertStatus(404);
    }
}
