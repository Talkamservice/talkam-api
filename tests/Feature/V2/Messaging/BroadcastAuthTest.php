<?php

namespace Tests\Feature\V2\Messaging;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * §3b shared fix: the v1 hole let any authenticated user subscribe to any
 * private channel.
 */
class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase, MessagingTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        // The log broadcaster skips channel authorization entirely — use the
        // pusher broadcaster with dummy creds so the channel callbacks run
        // (auth signing is local; no HTTP happens).
        config([
            "broadcasting.default" => "pusher",
            "broadcasting.connections.pusher.key" => "test-key",
            "broadcasting.connections.pusher.secret" => "test-secret",
            "broadcasting.connections.pusher.app_id" => "test-app",
        ]);

        // Channel callbacks were registered on the boot-time (log) driver;
        // re-register them on the now-default pusher driver.
        require base_path("routes/channels.php");
    }

    public function test_member_authorized_on_conversation_channel(): void
    {
        [$conversation, $a] = $this->conversationBetween();
        Sanctum::actingAs($a);

        $this->postJson("/broadcasting/auth", [
            "channel_name" => "private-private-conversation.{$conversation->id}",
            "socket_id" => "123.456",
        ])->assertStatus(200);
    }

    public function test_non_member_denied(): void
    {
        [$conversation] = $this->conversationBetween();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/broadcasting/auth", [
            "channel_name" => "private-private-conversation.{$conversation->id}",
            "socket_id" => "123.456",
        ])->assertStatus(403);
    }

    public function test_refresh_channel_only_for_own_user_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/broadcasting/auth", [
            "channel_name" => "private-refresh-notification.{$user->id}",
            "socket_id" => "123.456",
        ])->assertStatus(200);

        $other = User::factory()->create();
        $this->postJson("/broadcasting/auth", [
            "channel_name" => "private-refresh-notification.{$other->id}",
            "socket_id" => "123.456",
        ])->assertStatus(403);
    }
}
