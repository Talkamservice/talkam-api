<?php

namespace Tests\Feature\V2\TherapistDashboard;

use App\Constants\Therapist\TherapistConstants;
use App\Models\SessionNote;
use App\Models\Therapist;
use App\Models\TherapistReview;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    private function therapist(): array
    {
        $user = User::factory()->create();
        $therapist = Therapist::factory()->create(["user_id" => $user->id]);
        Sanctum::actingAs($user);

        return [$therapist, $user];
    }

    public function test_home_returns_the_next_session_kpis_and_employment_flag(): void
    {
        [$therapist] = $this->therapist();
        $client = User::factory()->create();

        $next = TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "starts_at" => now()->addHours(2),
            "status" => TherapistConstants::SESSION_CONFIRMED,
        ]);

        TherapySession::factory()->completed()->count(3)->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "starts_at" => now()->startOfWeek()->addDay(),
        ]);

        TherapistReview::factory()->create(["therapist_id" => $therapist->id, "rating" => 5]);

        $data = $this->getJson("/api/v2/therapist/home")->assertStatus(200)->json("data");

        $this->assertSame($next->id, $data["next_session"]["id"]);
        $this->assertStringStartsWith("#", $data["next_session"]["client_ref"]);
        $this->assertSame(3, $data["kpis"]["sessions_this_week"]);
        $this->assertEquals(5.0, $data["kpis"]["rating"]);
        $this->assertFalse($data["employment"]["is_business_employed"]);
        $this->assertSame(5, $data["latest_review"]["stars"]);
    }

    public function test_attention_counts_reflect_real_state(): void
    {
        [$therapist] = $this->therapist();
        $client = User::factory()->create();

        // Two completed sessions without a final note → two notes due.
        TherapySession::factory()->completed()->count(2)->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
        ]);

        // A third with a final note → not counted.
        $noted = TherapySession::factory()->completed()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
        ]);
        SessionNote::create([
            "session_id" => $noted->id,
            "therapist_id" => $therapist->id,
            "title" => "Done",
            "content" => "Written up.",
            "status" => "final",
            "shared_with_client" => false,
        ]);

        $attention = $this->getJson("/api/v2/therapist/home")->json("data.attention");

        $this->assertSame(2, $attention["pending_notes"]);
        $this->assertSame(0, $attention["reschedule_requests"]);
    }

    public function test_continuity_is_anonymous_and_carries_only_shared_notes(): void
    {
        [$therapist] = $this->therapist();
        $client = User::factory()->create(["first_name" => "Chidinma", "last_name" => "Eze"]);

        $past = TherapySession::factory()->completed()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "starts_at" => now()->subWeek(),
        ]);
        SessionNote::create([
            "session_id" => $past->id,
            "therapist_id" => $therapist->id,
            "title" => "Shared",
            "content" => "Revisit boundary-setting.",
            "status" => "final",
            "shared_with_client" => true,
        ]);

        TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "starts_at" => now()->addDay(),
            "status" => TherapistConstants::SESSION_CONFIRMED,
        ]);

        $response = $this->getJson("/api/v2/therapist/home")->assertStatus(200);
        $continuity = $response->json("data.continuity");

        $this->assertNotEmpty($continuity);
        $this->assertStringStartsWith("#", $continuity[0]["client_ref"]);
        $this->assertSame("Revisit boundary-setting.", $continuity[0]["shared_note"]);

        // The client's real name is never in the payload.
        $this->assertStringNotContainsString("Chidinma", $response->getContent());
    }

    public function test_home_never_shows_another_therapists_sessions(): void
    {
        [$therapist] = $this->therapist();
        $other = Therapist::factory()->create(["user_id" => User::factory()->create()->id]);

        TherapySession::factory()->create([
            "therapist_id" => $other->id,
            "user_id" => User::factory()->create()->id,
            "starts_at" => now()->addHour(),
            "status" => TherapistConstants::SESSION_CONFIRMED,
        ]);

        $this->getJson("/api/v2/therapist/home")
            ->assertStatus(200)
            ->assertJsonPath("data.next_session", null);
    }

    public function test_a_non_therapist_gets_403(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/therapist/home")->assertStatus(403);
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson("/api/v2/therapist/home")->assertStatus(401);
    }
}
