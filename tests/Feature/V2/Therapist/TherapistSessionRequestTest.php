<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapistSessionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistSessionRequestTest extends TestCase
{
    use RefreshDatabase;

    private function therapistWithMondayAvailability(): array
    {
        $therapist = Therapist::factory()->create();
        TherapistAvailability::factory()->create([
            "user_id" => $therapist->user_id,
            "day_of_week" => "monday",
            "start_time" => "09:00",
            "end_time" => "12:00",
        ]);

        return [$therapist, Carbon::parse("next monday 09:00")->toDateTimeString()];
    }

    public function test_client_submits_a_request(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/session-requests", [
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(3)->toDateTimeString(),
            "note" => "Afternoons work best",
        ])->assertStatus(200)->assertJsonPath("data.status", "pending");

        $this->assertDatabaseHas("therapist_session_requests", [
            "therapist_id" => $therapist->id,
            "status" => "pending",
        ]);
    }

    public function test_past_preferred_time_rejected(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/session-requests", [
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->subDay()->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_therapist_sees_only_their_own_pending_requests(): void
    {
        [$therapist] = $this->therapistWithMondayAvailability();
        $other = Therapist::factory()->create();

        TherapistSessionRequest::create([
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);
        TherapistSessionRequest::create([
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $other->id,
            "format" => "voice",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);

        $requests = $this->getJson("/api/v2/therapist/session-requests")
            ->assertStatus(200)
            ->json("data.requests");

        $this->assertCount(1, $requests);
    }

    public function test_plain_user_forbidden_from_therapist_queue(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v2/therapist/session-requests")->assertStatus(403);
    }

    public function test_propose_creates_real_session_and_clears_queue(): void
    {
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        $client = User::factory()->create();

        $request = TherapistSessionRequest::create([
            "user_id" => $client->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);

        $response = $this->postJson("/api/v2/therapist/session-requests/{$request->id}/propose", [
            "starts_at" => $monday,
        ])->assertStatus(200);

        $response->assertJsonPath("data.status", "proposed");
        $session_id = $response->json("data.session_id");

        $this->assertDatabaseHas("therapy_sessions", [
            "id" => $session_id,
            "user_id" => $client->id,
            "therapist_id" => $therapist->id,
            "status" => "pending_payment",
            "starts_at" => $monday,
        ]);

        $queue = $this->getJson("/api/v2/therapist/session-requests")->json("data.requests");
        $this->assertCount(0, $queue);
    }

    public function test_propose_rejects_a_slot_outside_availability(): void
    {
        [$therapist] = $this->therapistWithMondayAvailability();
        $request = TherapistSessionRequest::create([
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/session-requests/{$request->id}/propose", [
            "starts_at" => Carbon::parse("next tuesday 09:00")->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_propose_twice_rejected(): void
    {
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        $request = TherapistSessionRequest::create([
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);
        $this->postJson("/api/v2/therapist/session-requests/{$request->id}/propose", ["starts_at" => $monday])
            ->assertStatus(200);
        $this->postJson("/api/v2/therapist/session-requests/{$request->id}/propose", ["starts_at" => $monday])
            ->assertStatus(400);
    }

    public function test_therapist_declines_a_pending_request(): void
    {
        $therapist = Therapist::factory()->create();
        $request = TherapistSessionRequest::create([
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);
        $this->postJson("/api/v2/therapist/session-requests/{$request->id}/decline")
            ->assertStatus(200)
            ->assertJsonPath("data.status", "declined");
    }

    public function test_other_therapist_cannot_act_on_request(): void
    {
        $therapist = Therapist::factory()->create();
        $intruder = Therapist::factory()->create();
        $request = TherapistSessionRequest::create([
            "user_id" => User::factory()->create()->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($intruder->user);
        $this->postJson("/api/v2/therapist/session-requests/{$request->id}/decline")->assertStatus(404);
    }

    public function test_client_declines_proposed_time_and_session_is_cancelled(): void
    {
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        $client = User::factory()->create();
        $request = TherapistSessionRequest::create([
            "user_id" => $client->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);
        $session_id = $this->postJson("/api/v2/therapist/session-requests/{$request->id}/propose", ["starts_at" => $monday])
            ->json("data.session_id");

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/user/session-requests/{$request->id}/decline")
            ->assertStatus(200)
            ->assertJsonPath("data.status", "declined");

        $this->assertDatabaseHas("therapy_sessions", ["id" => $session_id, "status" => "cancelled"]);
    }

    public function test_client_sees_own_proposed_request(): void
    {
        [$therapist, $monday] = $this->therapistWithMondayAvailability();
        $client = User::factory()->create();
        $request = TherapistSessionRequest::create([
            "user_id" => $client->id,
            "therapist_id" => $therapist->id,
            "format" => "video",
            "preferred_at" => now()->addDays(2),
            "status" => "pending",
        ]);

        Sanctum::actingAs($therapist->user);
        $this->postJson("/api/v2/therapist/session-requests/{$request->id}/propose", ["starts_at" => $monday]);

        Sanctum::actingAs($client);
        $requests = $this->getJson("/api/v2/user/session-requests")->json("data.requests");

        $this->assertCount(1, $requests);
        $this->assertSame("proposed", $requests[0]["status"]);
        $this->assertSame($monday, $requests[0]["proposed_starts_at"]);
    }
}
