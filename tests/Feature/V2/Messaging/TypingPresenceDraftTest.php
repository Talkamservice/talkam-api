<?php

namespace Tests\Feature\V2\Messaging;

use App\Events\Messaging\UserPresenceChanged;
use App\Events\Messaging\UserTyping;
use App\Models\MessageDraft;
use App\Models\User;
use App\Models\UserPrivacySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TypingPresenceDraftTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_typing_broadcasts_and_activity_status_gating(): void
    {
        Event::fake([UserTyping::class]);
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/messages/typing", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(200);
        Event::assertDispatched(UserTyping::class);

        // Suppressed when activity_status is off.
        Event::fake([UserTyping::class]);
        UserPrivacySetting::create(["user_id" => $a->id, "activity_status" => false]);
        $this->postJson("/api/v2/user/messaging/messages/typing", [
            "conversation_id" => $conversation->id,
        ])->assertStatus(200);
        Event::assertNotDispatched(UserTyping::class);
    }

    public function test_presence_validates_states_and_gating(): void
    {
        Event::fake([UserPresenceChanged::class]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/messaging/presence", ["status" => "busy"])
            ->assertStatus(422);

        $this->postJson("/api/v2/user/messaging/presence", ["status" => "online"])
            ->assertStatus(200);
        Event::assertDispatched(UserPresenceChanged::class);

        Event::fake([UserPresenceChanged::class]);
        UserPrivacySetting::create(["user_id" => $user->id, "activity_status" => false]);
        $this->postJson("/api/v2/user/messaging/presence", ["status" => "away"])
            ->assertStatus(200);
        Event::assertNotDispatched(UserPresenceChanged::class);
    }

    public function test_save_draft_upserts_single_row(): void
    {
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/drafts/save", [
            "conversation_id" => $conversation->id,
            "content" => "First draft",
        ])->assertStatus(200);
        $this->postJson("/api/v2/user/messaging/drafts/save", [
            "conversation_id" => $conversation->id,
            "content" => "Second draft",
        ])->assertStatus(200);

        $drafts = MessageDraft::all();
        $this->assertCount(1, $drafts);
        $this->assertSame("Second draft", $drafts->first()->content);
    }

    public function test_drafts_scoped_to_owner(): void
    {
        [$conversation, $a, $b] = $this->conversationBetween();
        MessageDraft::create([
            "user_id" => $a->id,
            "conversation_id" => $conversation->id,
            "content" => "A's secret draft",
        ]);

        Sanctum::actingAs($b);
        $this->getJson("/api/v2/user/messaging/drafts/get?conversation_id={$conversation->id}")
            ->assertStatus(200)
            ->assertJsonPath("data", null);
    }
}
