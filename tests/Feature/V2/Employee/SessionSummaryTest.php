<?php

namespace Tests\Feature\V2\Employee;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_summary_counts_upcoming_and_completed(): void
    {
        $user = $this->actor();
        $therapist = Therapist::factory()->create();

        TherapySession::factory()->count(2)->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
            "starts_at" => now()->addDays(3),
            "status" => TherapistConstants::SESSION_CONFIRMED,
        ]);

        TherapySession::factory()->completed()->count(3)->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        $summary = $this->getJson("/api/v2/user/bookings")
            ->assertStatus(200)
            ->json("data.summary");

        $this->assertSame(2, $summary["upcoming"]);
        $this->assertSame(3, $summary["completed"]);
    }

    /** The §07 payload keys must survive the addition untouched. */
    public function test_the_existing_upcoming_and_past_keys_are_unchanged(): void
    {
        $user = $this->actor();
        $therapist = Therapist::factory()->create();

        TherapySession::factory()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
            "starts_at" => now()->addDay(),
        ]);
        TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        $data = $this->getJson("/api/v2/user/bookings")->assertStatus(200)->json("data");

        $this->assertCount(1, $data["upcoming"]);
        $this->assertCount(1, $data["past"]);
        $this->assertArrayHasKey("therapist_name", $data["upcoming"][0]);
        $this->assertArrayHasKey("receipt_url", $data["upcoming"][0]);
    }

    public function test_sessions_used_counts_only_this_quarter(): void
    {
        $user = $this->actor();
        $therapist = Therapist::factory()->create();

        TherapySession::factory()->completed()->count(2)->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
            "starts_at" => now()->firstOfQuarter()->addDay(),
        ]);

        // Last quarter — counted in `completed`, not in `sessions_used`.
        TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
            "starts_at" => now()->firstOfQuarter()->subMonth(),
        ]);

        $summary = $this->getJson("/api/v2/user/bookings")->json("data.summary");

        $this->assertSame(2, $summary["sessions_used"]);
        $this->assertSame(3, $summary["completed"]);
    }

    public function test_sessions_allowed_comes_from_the_company_bundle(): void
    {
        $user = $this->actor();
        $organization = Organization::factory()->create(["session_bundle_sessions" => 6]);

        OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
            "role" => OrganizationConstants::ROLE_EMPLOYEE,
        ]);

        $this->assertSame(
            6,
            $this->getJson("/api/v2/user/bookings")->json("data.summary.sessions_allowed")
        );
    }

    /** A direct (non-B2B) member has no cap to render. */
    public function test_sessions_allowed_is_null_without_an_organization(): void
    {
        $this->actor();

        $this->assertNull(
            $this->getJson("/api/v2/user/bookings")->json("data.summary.sessions_allowed")
        );
    }

    public function test_the_summary_never_counts_another_members_sessions(): void
    {
        $user = $this->actor();
        $other = User::factory()->create();
        $therapist = Therapist::factory()->create();

        TherapySession::factory()->completed()->count(5)->create([
            "user_id" => $other->id,
            "therapist_id" => $therapist->id,
        ]);
        TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        $summary = $this->getJson("/api/v2/user/bookings")->json("data.summary");

        $this->assertSame(1, $summary["completed"]);
    }

    /* ── Session mood ───────────────────────────────────────────────────── */

    public function test_pre_and_post_mood_are_stored_independently(): void
    {
        $user = $this->actor();
        $session = TherapySession::factory()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "pre",
            "mood" => 2,
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.client_pre_mood", 2)
            ->assertJsonPath("data.client_post_mood", null);

        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "post",
            "mood" => 4,
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.client_pre_mood", 2)
            ->assertJsonPath("data.client_post_mood", 4);

        $session->refresh();
        $this->assertSame(2, (int) $session->client_pre_mood);
        $this->assertSame(4, (int) $session->client_post_mood);
    }

    public function test_session_mood_surfaces_on_the_booking_payload(): void
    {
        $user = $this->actor();
        $session = TherapySession::factory()->completed()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "pre",
            "mood" => 2,
        ])->assertStatus(200);

        $past = $this->getJson("/api/v2/user/bookings")->json("data.past");

        $this->assertSame(2, $past[0]["client_pre_mood"]);
        $this->assertNull($past[0]["client_post_mood"]);
    }

    public function test_session_mood_rejects_an_unknown_phase_or_out_of_range_mood(): void
    {
        $user = $this->actor();
        $session = TherapySession::factory()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "midway",
            "mood" => 3,
        ])->assertStatus(422);

        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "pre",
            "mood" => 9,
        ])->assertStatus(422);

        $session->refresh();
        $this->assertNull($session->client_pre_mood);
    }

    public function test_session_mood_on_another_members_booking_is_not_found(): void
    {
        $this->actor();
        $other = User::factory()->create();
        $session = TherapySession::factory()->create(["user_id" => $other->id]);

        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "pre",
            "mood" => 5,
        ])->assertStatus(404);

        $this->assertNull($session->refresh()->client_pre_mood);
    }

    public function test_endpoints_require_authentication(): void
    {
        $session = TherapySession::factory()->create();

        $this->getJson("/api/v2/user/bookings")->assertStatus(401);
        $this->postJson("/api/v2/user/bookings/{$session->id}/session-mood", [
            "phase" => "pre",
            "mood" => 3,
        ])->assertStatus(401);
    }
}
