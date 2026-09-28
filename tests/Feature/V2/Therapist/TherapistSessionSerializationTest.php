<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\SessionNote;
use App\Models\TherapistReview;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistSessionSerializationTest extends TestCase
{
    use RefreshDatabase;

    /** has_note (not the client's own `notes` booking field) drives the
     *  dashboard's "Notes due" count and past-tab badge. */
    public function test_has_note_true_only_once_a_final_note_exists(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $before = $this->getJson("/api/v2/therapist/sessions")->json("data.past");
        $this->assertFalse($before[0]["has_note"]);

        SessionNote::create([
            "session_id" => $session->id,
            "therapist_id" => $session->therapist_id,
            "title" => "Follow-up",
            "status" => "draft",
        ]);
        $draft = $this->getJson("/api/v2/therapist/sessions")->json("data.past");
        $this->assertFalse($draft[0]["has_note"]);

        SessionNote::where("session_id", $session->id)->update(["status" => "final"]);
        $after = $this->getJson("/api/v2/therapist/sessions")->json("data.past");
        $this->assertTrue($after[0]["has_note"]);
    }

    public function test_earnings_is_net_of_config_share(): void
    {
        $session = TherapySession::factory()->completed()->create(["amount" => 25000]);
        Sanctum::actingAs($session->therapist->user);

        $share = config("therapist.platform_share_percent");
        $expected_net = round(25000 * (1 - $share / 100), 2);

        $past = $this->getJson("/api/v2/therapist/sessions")->assertStatus(200)->json("data.past");

        $this->assertEquals($expected_net, $past[0]["earnings"]);
    }

    public function test_client_rating_included_when_review_exists(): void
    {
        $session = TherapySession::factory()->completed()->create();
        TherapistReview::factory()->create([
            "session_id" => $session->id,
            "therapist_id" => $session->therapist_id,
            "rating" => 4,
        ]);
        Sanctum::actingAs($session->therapist->user);

        $past = $this->getJson("/api/v2/therapist/sessions")->json("data.past");

        $this->assertSame(4, $past[0]["rating"]);
    }
}
