<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\Therapist\SessionAcknowledgedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionAcknowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledge_stamps_timestamp_without_changing_status(): void
    {
        Notification::fake();
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")
            ->assertStatus(200);

        $session->refresh();
        $this->assertNotNull($session->acknowledged_at);
        // Key §3a criterion: booking stays confirmed.
        $this->assertSame("confirmed", $session->status);
    }

    public function test_acknowledge_notifies_client(): void
    {
        Notification::fake();
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge");

        Notification::assertSentTo($session->user, SessionAcknowledgedNotification::class);
    }

    public function test_second_acknowledge_keeps_original_timestamp(): void
    {
        Notification::fake();
        $session = TherapySession::factory()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")->assertStatus(200);
        $first = $session->refresh()->acknowledged_at;

        $this->travel(5)->minutes();
        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")->assertStatus(200);

        $this->assertEquals($first, $session->refresh()->acknowledged_at);
    }

    public function test_plain_user_forbidden(): void
    {
        $session = TherapySession::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")->assertStatus(403);
    }

    public function test_unassigned_therapist_forbidden(): void
    {
        $session = TherapySession::factory()->create();
        $other = TherapySession::factory()->create();
        Sanctum::actingAs($other->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/acknowledge")->assertStatus(404);
    }
}
