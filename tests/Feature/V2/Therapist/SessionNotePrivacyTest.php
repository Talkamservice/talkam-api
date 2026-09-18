<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\SessionNote;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionNotePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_therapist_can_write_note(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Sleep improvement",
            "content" => "Client reports better sleep. CBT journaling assigned.",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("session_notes", [
            "session_id" => $session->id,
            "title" => "Sleep improvement",
        ]);
    }

    public function test_note_private_by_default(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Private note",
        ])->assertStatus(200);

        $this->assertDatabaseHas("session_notes", [
            "session_id" => $session->id,
            "shared_with_client" => 0,
        ]);
    }

    public function test_client_cannot_read_unshared_note(): void
    {
        $session = TherapySession::factory()->completed()->create();
        SessionNote::create([
            "session_id" => $session->id,
            "therapist_id" => $session->therapist_id,
            "title" => "Secret clinical note",
            "shared_with_client" => false,
        ]);

        Sanctum::actingAs($session->user);
        $this->getJson("/api/v2/user/bookings/{$session->id}")
            ->assertStatus(200)
            ->assertJsonPath("data.shared_note", null);
    }

    public function test_shared_note_visible_to_client(): void
    {
        $session = TherapySession::factory()->completed()->create();
        SessionNote::create([
            "session_id" => $session->id,
            "therapist_id" => $session->therapist_id,
            "title" => "Homework for next week",
            "content" => "Practice breathing exercises daily.",
            "shared_with_client" => true,
        ]);

        Sanctum::actingAs($session->user);
        $this->getJson("/api/v2/user/bookings/{$session->id}")
            ->assertJsonPath("data.shared_note.title", "Homework for next week");
    }

    public function test_other_therapist_cannot_read_or_write_note(): void
    {
        $session = TherapySession::factory()->completed()->create();
        $other = TherapySession::factory()->create(); // different therapist
        Sanctum::actingAs($other->therapist->user);

        $this->getJson("/api/v2/therapist/sessions/{$session->id}/notes")->assertStatus(404);
        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Should not work",
        ])->assertStatus(404);

        $this->assertSame(0, SessionNote::count());
    }
}
