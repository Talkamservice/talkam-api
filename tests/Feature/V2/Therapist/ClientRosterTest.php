<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_lists_distinct_clients_with_confirmed_or_completed_sessions(): void
    {
        $therapist = Therapist::factory()->create();
        $client = User::factory()->create();
        TherapySession::factory()->create(["therapist_id" => $therapist->id, "user_id" => $client->id]);
        TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id, "user_id" => $client->id]);

        Sanctum::actingAs($therapist->user);
        $data = $this->getJson("/api/v2/therapist/clients")->assertStatus(200)->json("data");

        $this->assertCount(1, $data);
        $this->assertSame($client->id, $data[0]["id"]);
        $this->assertSame(2, $data[0]["sessions_count"]);
    }

    public function test_pending_payment_session_creates_no_client(): void
    {
        $therapist = Therapist::factory()->create();
        TherapySession::factory()->pending()->create(["therapist_id" => $therapist->id]);

        Sanctum::actingAs($therapist->user);
        $this->assertEmpty($this->getJson("/api/v2/therapist/clients")->json("data"));
    }

    public function test_roster_excludes_other_therapists_clients(): void
    {
        $therapist_a = Therapist::factory()->create();
        $therapist_b = Therapist::factory()->create();
        $b_client = User::factory()->create();
        TherapySession::factory()->create(["therapist_id" => $therapist_b->id, "user_id" => $b_client->id]);

        Sanctum::actingAs($therapist_a->user);
        $ids = collect($this->getJson("/api/v2/therapist/clients")->json("data"))->pluck("id");

        $this->assertFalse($ids->contains($b_client->id));
    }

    public function test_plain_user_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v2/therapist/clients")->assertStatus(403);
    }

    public function test_guest_unauthorized(): void
    {
        $this->getJson("/api/v2/therapist/clients")->assertStatus(401);
    }
}
