<?php

namespace Tests\Feature\V2\Employee;

use App\Constants\Therapist\TherapistConstants;
use App\Models\SessionNote;
use App\Models\Therapist;
use App\Models\TherapistReview;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareTeamTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function therapist(string $name = "Adewale Kolawole"): Therapist
    {
        [$first, $last] = explode(" ", $name);

        return Therapist::factory()->create([
            "user_id" => User::factory()->create([
                "first_name" => $first,
                "last_name" => $last,
            ])->id,
            "credential_type" => "Clinical Psychologist",
        ]);
    }

    public function test_a_member_with_no_sessions_gets_a_null_therapist_not_a_404(): void
    {
        $this->actor();

        $this->getJson("/api/v2/user/care-team")
            ->assertStatus(200)
            ->assertJsonPath("data.therapist", null)
            ->assertJsonPath("data.continuity_note", null)
            ->assertJsonPath("data.next_session_id", null);
    }

    public function test_returns_the_most_recent_therapist_with_a_session_count(): void
    {
        $user = $this->actor();
        $older = $this->therapist("Chioma Obi");
        $recent = $this->therapist("Adewale Kolawole");

        TherapySession::factory()->completed()->count(2)->create([
            "user_id" => $user->id,
            "therapist_id" => $older->id,
            "starts_at" => now()->subMonth(),
        ]);

        TherapySession::factory()->completed()->count(4)->create([
            "user_id" => $user->id,
            "therapist_id" => $recent->id,
            "starts_at" => now()->subDays(3),
        ]);

        $data = $this->getJson("/api/v2/user/care-team")->assertStatus(200)->json("data");

        $this->assertSame($recent->id, $data["therapist"]["id"]);
        $this->assertSame("Adewale Kolawole", $data["therapist"]["name"]);
        $this->assertSame("AK", $data["therapist"]["initials"]);
        $this->assertSame("Clinical Psychologist", $data["therapist"]["credential_type"]);
        $this->assertSame(4, $data["therapist"]["sessions_together"]);
    }

    public function test_an_upcoming_session_sets_next_session_id_and_wins_recency(): void
    {
        $user = $this->actor();
        $therapist = $this->therapist();

        $upcoming = TherapySession::factory()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
            "starts_at" => now()->addDays(2),
            "status" => TherapistConstants::SESSION_CONFIRMED,
        ]);

        $data = $this->getJson("/api/v2/user/care-team")->json("data");

        $this->assertSame($therapist->id, $data["therapist"]["id"]);
        $this->assertSame($upcoming->id, $data["next_session_id"]);
        // No completed sessions yet.
        $this->assertSame(0, $data["therapist"]["sessions_together"]);
    }

    public function test_rating_is_the_mean_of_the_therapists_reviews(): void
    {
        $user = $this->actor();
        $therapist = $this->therapist();

        TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        TherapistReview::factory()->create(["therapist_id" => $therapist->id, "rating" => 5]);
        TherapistReview::factory()->create(["therapist_id" => $therapist->id, "rating" => 4]);

        $this->assertEquals(
            4.5,
            $this->getJson("/api/v2/user/care-team")->json("data.therapist.rating")
        );
    }

    public function test_rating_is_null_with_no_reviews(): void
    {
        $user = $this->actor();
        $therapist = $this->therapist();

        TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        $this->assertNull($this->getJson("/api/v2/user/care-team")->json("data.therapist.rating"));
    }

    /* ── The continuity note is the privacy-sensitive bit ───────────────── */

    public function test_a_shared_note_becomes_the_continuity_note(): void
    {
        $user = $this->actor();
        $therapist = $this->therapist();

        $session = TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        SessionNote::create([
            "session_id" => $session->id,
            "therapist_id" => $therapist->id,
            "title" => "Session 4",
            "content" => "Continuing work on boundary-setting and box-breathing.",
            "status" => "final",
            "shared_with_client" => true,
        ]);

        $this->assertSame(
            "Continuing work on boundary-setting and box-breathing.",
            $this->getJson("/api/v2/user/care-team")->json("data.continuity_note")
        );
    }

    /** A private clinical note must never reach the client. */
    public function test_a_private_note_never_leaks(): void
    {
        $user = $this->actor();
        $therapist = $this->therapist();

        $session = TherapySession::factory()->completed()->create([
            "user_id" => $user->id,
            "therapist_id" => $therapist->id,
        ]);

        SessionNote::create([
            "session_id" => $session->id,
            "therapist_id" => $therapist->id,
            "title" => "Private",
            "content" => "Clinical impression the client must not see.",
            "status" => "final",
            "shared_with_client" => false,
        ]);

        $response = $this->getJson("/api/v2/user/care-team")->assertStatus(200);

        $this->assertNull($response->json("data.continuity_note"));
        $this->assertStringNotContainsString("must not see", $response->getContent());
    }

    public function test_the_card_never_reflects_another_members_sessions(): void
    {
        $this->actor();
        $other = User::factory()->create();
        $therapist = $this->therapist();

        TherapySession::factory()->completed()->count(3)->create([
            "user_id" => $other->id,
            "therapist_id" => $therapist->id,
        ]);

        $this->getJson("/api/v2/user/care-team")
            ->assertStatus(200)
            ->assertJsonPath("data.therapist", null);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/care-team")->assertStatus(401);
    }
}
