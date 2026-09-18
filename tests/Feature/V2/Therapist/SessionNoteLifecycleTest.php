<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\PostCategory;
use App\Models\SessionNote;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionNoteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_post_upserts_single_row_per_session(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Draft one",
            "status" => "draft",
        ])->assertStatus(200);
        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Draft two",
            "status" => "draft",
        ])->assertStatus(200);

        $notes = SessionNote::all();
        $this->assertCount(1, $notes);
        $this->assertSame("Draft two", $notes->first()->title);
        $this->assertSame("draft", $notes->first()->status);
    }

    public function test_save_and_close_marks_note_final(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Final note",
            "status" => "final",
        ])->assertStatus(200);

        $this->assertDatabaseHas("session_notes", [
            "session_id" => $session->id,
            "status" => "final",
        ]);
    }

    public function test_final_note_editable_and_updated_at_surfaced(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $first = $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Original",
            "status" => "final",
        ])->json("data.updated_at");

        $this->travel(10)->minutes();
        $second = $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Edited after finalizing",
            "status" => "final",
        ])->assertStatus(200)->json("data.updated_at");

        $this->assertNotEquals($first, $second);
        $this->assertSame("Edited after finalizing", SessionNote::first()->title);
    }

    public function test_unknown_tag_id_rejected(): void
    {
        $session = TherapySession::factory()->completed()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Tagged",
            "tags" => [999999],
        ])->assertStatus(422);
    }

    public function test_valid_topic_ids_accepted(): void
    {
        $session = TherapySession::factory()->completed()->create();
        $topic = PostCategory::factory()->interestTopic()->create();
        Sanctum::actingAs($session->therapist->user);

        $this->postJson("/api/v2/therapist/sessions/{$session->id}/notes", [
            "title" => "Tagged",
            "tags" => [$topic->id],
        ])->assertStatus(200);

        $this->assertEquals([$topic->id], SessionNote::first()->tags);
    }
}
