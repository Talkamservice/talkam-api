<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\ClientTreatmentPlan;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreatmentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function therapistWithClient(): array
    {
        $therapist = Therapist::factory()->create();
        $client = User::factory()->create();
        TherapySession::factory()->create(["therapist_id" => $therapist->id, "user_id" => $client->id]);

        return [$therapist, $client];
    }

    public function test_post_creates_plan_row(): void
    {
        [$therapist, $client] = $this->therapistWithClient();
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [
            "total_sessions" => 8,
            "progress_status" => "good_progress",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("client_treatment_plans", [
            "therapist_id" => $therapist->id,
            "user_id" => $client->id,
            "total_sessions" => 8,
        ]);
    }

    public function test_second_post_updates_same_row_not_duplicate(): void
    {
        [$therapist, $client] = $this->therapistWithClient();
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [
            "total_sessions" => 8,
            "progress_status" => "good_progress",
        ]);
        $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [
            "total_sessions" => 12,
            "progress_status" => "steady",
        ])->assertStatus(200);

        $plans = ClientTreatmentPlan::all();
        $this->assertCount(1, $plans);
        $this->assertSame(12, $plans->first()->total_sessions);
    }

    public function test_invalid_progress_status_rejected(): void
    {
        [$therapist, $client] = $this->therapistWithClient();
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [
            "total_sessions" => 8,
            "progress_status" => "stellar",
        ])->assertStatus(422);
    }

    public function test_non_positive_total_sessions_rejected(): void
    {
        [$therapist, $client] = $this->therapistWithClient();
        Sanctum::actingAs($therapist->user);

        foreach ([0, -3, null] as $bad) {
            $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [
                "total_sessions" => $bad,
                "progress_status" => "good_progress",
            ])->assertStatus(422);
        }
    }

    public function test_other_therapist_cannot_set_plan(): void
    {
        [, $client] = $this->therapistWithClient();
        $other = Therapist::factory()->create();
        Sanctum::actingAs($other->user);

        $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [
            "total_sessions" => 8,
            "progress_status" => "good_progress",
        ])->assertStatus(404);
    }

    public function test_plain_user_forbidden(): void
    {
        [, $client] = $this->therapistWithClient();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/clients/{$client->id}/treatment-plan", [])
            ->assertStatus(403);
    }
}
