<?php

namespace Tests\Feature\V2\TherapistDashboard;

use App\Constants\Therapist\TherapistConstants;
use App\Models\TherapistAvailability;
use App\Models\Therapist;
use App\Models\TherapistReview;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function therapist(): array
    {
        $user = User::factory()->create();
        $therapist = Therapist::factory()->create(["user_id" => $user->id]);
        Sanctum::actingAs($user);

        return [$therapist, $user];
    }

    /* ── Analytics ──────────────────────────────────────────────────────── */

    public function test_analytics_returns_every_panel_for_the_default_range(): void
    {
        [$therapist] = $this->therapist();
        $client = User::factory()->create();

        TherapySession::factory()->completed()->count(4)->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "starts_at" => now()->subDays(3),
            "client_post_mood" => 4,
        ]);
        TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "starts_at" => now()->subDays(2),
            "status" => TherapistConstants::SESSION_NO_SHOW,
        ]);
        TherapistReview::factory()->create(["therapist_id" => $therapist->id, "rating" => 5]);

        $data = $this->getJson("/api/v2/therapist/analytics?range=4w")->assertStatus(200)->json("data");

        $this->assertSame("4w", $data["range"]);
        $this->assertSame(28, $data["window_days"]);
        $this->assertSame(4, $data["kpis"]["total_sessions"]);
        $this->assertSame(80, $data["kpis"]["completion_rate"]); // 4 of 5
        $this->assertSame(1, $data["kpis"]["no_shows"]);
        $this->assertArrayHasKey("session_bars", $data);
        $this->assertArrayHasKey("outcome_trend", $data);
        $this->assertArrayHasKey("busiest_slots", $data);
        $this->assertCount(5, $data["rating_breakdown"]);
        $this->assertCount(7, $data["busiest_slots"]);
    }

    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $this->therapist();

        $this->getJson("/api/v2/therapist/analytics?range=nonsense")
            ->assertStatus(200)
            ->assertJsonPath("data.window_days", 28);
    }

    public function test_the_range_window_changes_the_span(): void
    {
        $this->therapist();

        $this->assertSame(84, $this->getJson("/api/v2/therapist/analytics?range=12w")->json("data.window_days"));
        $this->assertSame(182, $this->getJson("/api/v2/therapist/analytics?range=6m")->json("data.window_days"));
    }

    public function test_analytics_are_the_therapists_own(): void
    {
        [$therapist] = $this->therapist();
        $other = Therapist::factory()->create(["user_id" => User::factory()->create()->id]);

        TherapySession::factory()->completed()->count(5)->create([
            "therapist_id" => $other->id,
            "user_id" => User::factory()->create()->id,
            "starts_at" => now()->subDay(),
        ]);

        $this->getJson("/api/v2/therapist/analytics")
            ->assertStatus(200)
            ->assertJsonPath("data.kpis.total_sessions", 0);
    }

    public function test_analytics_forbidden_for_a_non_therapist(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v2/therapist/analytics")->assertStatus(403);
    }

    /* ── Availability ───────────────────────────────────────────────────── */

    public function test_availability_round_trips_the_weekly_grid(): void
    {
        [, $user] = $this->therapist();

        $payload = [
            "days" => [
                "mon" => [
                    ["start" => "09:00", "end" => "09:50", "active" => true],
                    ["start" => "11:00", "end" => "11:50", "active" => true],
                ],
                "wed" => [
                    ["start" => "14:00", "end" => "14:50", "active" => true],
                ],
            ],
        ];

        $this->putJson("/api/v2/therapist/availability", $payload)->assertStatus(200);

        $grid = $this->getJson("/api/v2/therapist/availability")->assertStatus(200)->json("data");

        $this->assertTrue($grid["days"]["mon"]);
        $this->assertTrue($grid["days"]["wed"]);
        $this->assertFalse($grid["days"]["tue"]);
        $this->assertCount(2, $grid["slots"]["mon"]);
        $this->assertSame("09:00", $grid["slots"]["mon"][0]["start"]);

        $this->assertSame(3, TherapistAvailability::where("user_id", $user->id)->count());
    }

    public function test_a_put_replaces_the_grid(): void
    {
        [, $user] = $this->therapist();

        $this->putJson("/api/v2/therapist/availability", [
            "days" => ["mon" => [["start" => "09:00", "end" => "09:50"]]],
        ])->assertStatus(200);

        $this->putJson("/api/v2/therapist/availability", [
            "days" => ["fri" => [["start" => "10:00", "end" => "10:50"]]],
        ])->assertStatus(200);

        $grid = $this->getJson("/api/v2/therapist/availability")->json("data");

        $this->assertFalse($grid["days"]["mon"]);
        $this->assertTrue($grid["days"]["fri"]);
        $this->assertSame(1, TherapistAvailability::where("user_id", $user->id)->count());
    }

    public function test_editing_availability_does_not_touch_booked_sessions(): void
    {
        [$therapist, $user] = $this->therapist();

        $booked = TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "user_id" => User::factory()->create()->id,
            "starts_at" => now()->addWeek()->setTime(9, 0),
            "status" => TherapistConstants::SESSION_CONFIRMED,
        ]);

        $this->putJson("/api/v2/therapist/availability", [
            "days" => ["mon" => []],
        ])->assertStatus(200);

        // The concrete booking is untouched by a grid edit.
        $booked->refresh();
        $this->assertSame(TherapistConstants::SESSION_CONFIRMED, $booked->status);
        $this->assertNotNull($booked->starts_at);
    }

    public function test_availability_rejects_a_bad_time_or_unknown_day(): void
    {
        $this->therapist();

        $this->putJson("/api/v2/therapist/availability", [
            "days" => ["mon" => [["start" => "10:00", "end" => "09:00"]]], // end before start
        ])->assertStatus(422);

        $this->putJson("/api/v2/therapist/availability", [
            "days" => ["someday" => [["start" => "09:00", "end" => "10:00"]]],
        ])->assertStatus(422);
    }

    public function test_availability_forbidden_for_a_non_therapist(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/therapist/availability")->assertStatus(403);
        $this->putJson("/api/v2/therapist/availability", ["days" => []])->assertStatus(403);
    }
}
