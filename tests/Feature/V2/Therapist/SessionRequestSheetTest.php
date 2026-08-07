<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\PostCategory;
use App\Models\TherapySession;
use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionRequestSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_sheet_returns_session_topics_note_and_net_earnings(): void
    {
        $session = TherapySession::factory()->create([
            "amount" => 20000,
            "notes" => "I'm a workaholic",
        ]);
        $topic = PostCategory::factory()->interestTopic()->create(["name" => "Anxiety"]);
        UserInterest::create(["user_id" => $session->user_id, "category_id" => $topic->id]);

        Sanctum::actingAs($session->therapist->user);
        $share = config("therapist.platform_share_percent");
        $expected_net = round(20000 * (1 - $share / 100), 2);

        $response = $this->getJson("/api/v2/therapist/sessions/{$session->id}/request")
            ->assertStatus(200)
            ->assertJsonPath("data.note", "I'm a workaholic")
            ->assertJsonPath("data.topics.0", "Anxiety");

        $this->assertEquals($expected_net, $response->json("data.you_receive"));
    }

    public function test_new_client_flag_true_without_prior_sessions(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->getJson("/api/v2/therapist/sessions/{$session->id}/request")
            ->assertJsonPath("data.new_client", true);
    }

    public function test_new_client_flag_false_with_prior_completed_session(): void
    {
        $session = TherapySession::factory()->create();
        TherapySession::factory()->completed()->create([
            "user_id" => $session->user_id,
            "therapist_id" => $session->therapist_id,
        ]);
        Sanctum::actingAs($session->therapist->user);

        $this->getJson("/api/v2/therapist/sessions/{$session->id}/request")
            ->assertJsonPath("data.new_client", false);
    }

    public function test_plain_user_forbidden(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/therapist/sessions/{$session->id}/request")->assertStatus(403);
    }

    public function test_unassigned_therapist_forbidden(): void
    {
        $session = TherapySession::factory()->create();
        $other = TherapySession::factory()->create(); // creates another therapist
        Sanctum::actingAs($other->therapist->user);

        $this->getJson("/api/v2/therapist/sessions/{$session->id}/request")->assertStatus(404);
    }

    public function test_guest_unauthorized(): void
    {
        $this->getJson("/api/v2/therapist/sessions/1/request")->assertStatus(401);
    }
}
