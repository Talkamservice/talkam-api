<?php

namespace Tests\Feature\V2\Messaging;

use App\Events\Messaging\MessageDelivered;
use App\Models\BlockedUser;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMute;
use App\Notifications\Messaging\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    public function test_member_sends_text_and_delivered_at_is_set(): void
    {
        Event::fake([MessageDelivered::class]);
        Notification::fake();
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => "Hello there",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $message = Message::first();
        $this->assertNotNull($message->delivered_at);
        $this->assertFalse($message->read);
        Event::assertDispatched(MessageDelivered::class);
    }

    /**
     * ReceiveMessage/MessageDelivered must broadcast synchronously
     * (ShouldBroadcastNow), not via the queue — this app has no `jobs`
     * table migration and no queue worker running, so a queued broadcast
     * event 500s the whole request the moment it tries to push the job.
     * Real events (no Event::fake) against a `database` queue connection
     * is the only way to actually exercise that failure mode.
     */
    public function test_send_does_not_touch_the_queue(): void
    {
        config(["queue.default" => "database"]);
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => "Hello there",
        ])->assertStatus(200)->assertJson(["success" => true]);
    }

    public function test_body_over_config_max_rejected_and_config_driven(): void
    {
        Notification::fake();
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $max = config("v2.messaging.max_length");
        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => str_repeat("a", $max + 1),
        ])->assertStatus(422);
        $this->assertSame(0, Message::count());

        // Config override moves the cap.
        config(["v2.messaging.max_length" => 10]);
        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => str_repeat("a", 11),
        ])->assertStatus(422);
    }

    public function test_blocked_sender_gets_403_and_no_row(): void
    {
        Notification::fake();
        [$conversation, $a, $b] = $this->conversationBetween();
        BlockedUser::create(["blocker_id" => $b->id, "blocked_user_id" => $a->id]);
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => "Should not go through",
        ])->assertStatus(403)->assertJson(["success" => false]);

        $this->assertSame(0, Message::count());
    }

    public function test_muted_sender_stores_message_but_no_notification(): void
    {
        Notification::fake();
        [$conversation, $a, $b] = $this->conversationBetween();
        UserMute::create(["user_id" => $b->id, "muted_user_id" => $a->id]);
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => "Stored silently",
        ])->assertStatus(200);

        $this->assertSame(1, Message::count());
        // Notification sent but via() resolves to no channels: assert no
        // channel delivery by checking via() directly.
        $notification = new NewMessageNotification(Message::first());
        $this->assertSame([], $notification->via($b));
    }

    public function test_non_member_cannot_send(): void
    {
        Notification::fake();
        [$conversation] = $this->conversationBetween();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "message" => "Intruder",
        ])->assertStatus(404);

        $this->assertSame(0, Message::count());
    }

    public function test_file_upload_creates_file_row_and_caps_enforced(): void
    {
        Notification::fake();
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        // Over the config cap → 422.
        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "file" => UploadedFile::fake()->create("big.pdf", config("v2.messaging.file_max_kb") + 1),
        ])->assertStatus(422);

        // Valid file → files row + file_id.
        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "file" => UploadedFile::fake()->create("voice.pdf", 100),
            "message_type" => "file",
        ])->assertStatus(200);

        $this->assertNotNull(Message::first()->file_id);
        $this->assertDatabaseHas("files", ["file_group" => "message-attachments"]);
    }

    public function test_voice_message_stores_duration(): void
    {
        Notification::fake();
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $this->postJson("/api/v2/user/messaging/messages/send", [
            "conversation_id" => $conversation->id,
            "file" => UploadedFile::fake()->create("note.pdf", 60),
            "message_type" => "voice",
            "voice_duration" => 42,
        ])->assertStatus(200);

        $this->assertSame(42, Message::first()->voice_duration);
    }
}
