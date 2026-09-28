<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\SessionNote;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotesLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function noteFor(TherapySession $session, string $title, string $content = ""): SessionNote
    {
        return SessionNote::create([
            "session_id" => $session->id,
            "therapist_id" => $session->therapist_id,
            "title" => $title,
            "content" => $content,
        ]);
    }

    public function test_list_returns_only_own_notes(): void
    {
        $own_session = TherapySession::factory()->completed()->create();
        $this->noteFor($own_session, "My note");
        $foreign_session = TherapySession::factory()->completed()->create();
        $this->noteFor($foreign_session, "Foreign note");

        Sanctum::actingAs($own_session->therapist->user);
        $titles = collect($this->getJson("/api/v2/therapist/notes")->assertStatus(200)->json("data.data"))
            ->pluck("title");

        $this->assertTrue($titles->contains("My note"));
        $this->assertFalse($titles->contains("Foreign note"));
    }

    public function test_client_filter_and_search_match_title_and_body(): void
    {
        $session_a = TherapySession::factory()->completed()->create();
        $therapist = $session_a->therapist;
        $session_b = TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id]);
        $this->noteFor($session_a, "Sleep improvement", "CBT journaling assigned");
        $this->noteFor($session_b, "Panic management", "Breathing exercises");

        Sanctum::actingAs($therapist->user);

        // Client filter narrows to that client's sessions.
        $filtered = collect($this->getJson("/api/v2/therapist/notes?client_id={$session_a->user_id}")
            ->json("data.data"))->pluck("title");
        $this->assertEquals(["Sleep improvement"], $filtered->all());

        // q= matches body text too.
        $searched = collect($this->getJson("/api/v2/therapist/notes?q=Breathing")->json("data.data"))
            ->pluck("title");
        $this->assertEquals(["Panic management"], $searched->all());
    }

    public function test_other_therapists_note_not_accessible(): void
    {
        $session = TherapySession::factory()->completed()->create();
        $note = $this->noteFor($session, "Private");
        $other = TherapySession::factory()->create();

        Sanctum::actingAs($other->therapist->user);
        $this->getJson("/api/v2/therapist/notes/{$note->id}")->assertStatus(404);
    }

    public function test_plain_user_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v2/therapist/notes")->assertStatus(403);
    }

    public function test_guest_unauthorized(): void
    {
        $this->getJson("/api/v2/therapist/notes")->assertStatus(401);
    }
}
