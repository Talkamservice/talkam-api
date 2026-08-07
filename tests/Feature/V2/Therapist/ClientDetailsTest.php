<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\ClientTreatmentPlan;
use App\Models\SessionNote;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_returns_client_since_stats_plan_and_history(): void
    {
        $therapist = Therapist::factory()->create();
        $client = User::factory()->create();
        $completed = TherapySession::factory()->completed()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
        ]);
        TherapySession::factory()->create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
        ]);
        ClientTreatmentPlan::create([
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "total_sessions" => 8,
            "progress_status" => "good_progress",
        ]);
        SessionNote::create([
            "session_id" => $completed->id,
            "therapist_id" => $therapist->id,
            "title" => "Sleep improvement - CBT journaling assigned",
        ]);

        Sanctum::actingAs($therapist->user);
        $data = $this->getJson("/api/v2/therapist/clients/{$client->id}")
            ->assertStatus(200)->json("data");

        $this->assertNotEmpty($data["client_since"]);
        $this->assertSame(2, $data["sessions_count"]);
        $this->assertSame(1, $data["completed_sessions"]);
        $this->assertSame(8, $data["treatment_plan"]["total_sessions"]);
        $this->assertEquals(round(1 / 8, 2), $data["treatment_plan"]["progress"]);
        $summaries = collect($data["session_history"])->pluck("summary")->filter();
        $this->assertTrue($summaries->contains("Sleep improvement - CBT journaling assigned"));
    }

    public function test_non_client_user_returns_404(): void
    {
        $therapist = Therapist::factory()->create();
        $stranger = User::factory()->create();

        Sanctum::actingAs($therapist->user);
        $this->getJson("/api/v2/therapist/clients/{$stranger->id}")->assertStatus(404);
    }

    public function test_other_therapists_client_not_accessible(): void
    {
        $therapist_a = Therapist::factory()->create();
        $therapist_b = Therapist::factory()->create();
        $b_client = User::factory()->create();
        TherapySession::factory()->create(["therapist_id" => $therapist_b->id, "user_id" => $b_client->id]);

        Sanctum::actingAs($therapist_a->user);
        $this->getJson("/api/v2/therapist/clients/{$b_client->id}")->assertStatus(404);
    }
}
