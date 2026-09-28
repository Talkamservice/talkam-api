<?php

namespace Tests\Feature\V2\Profile;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_superset_defaults_on_without_sms_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $data = $this->getJson("/api/v2/user/notification-preferences")
            ->assertStatus(200)->json("data");

        foreach ([
            "session_confirmation", "session_reminders", "post_session_feedback",
            "payment_confirmations", "replies_to_posts", "promotions_updates",
        ] as $key) {
            $this->assertTrue($data[$key], "$key should default on");
        }
        $this->assertArrayNotHasKey("can_receive_sms", $data);
    }

    public function test_post_toggles_each_new_key(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/notification-preferences", [
            "session_reminders" => false,
            "promotions_updates" => false,
        ])->assertStatus(200)
            ->assertJsonPath("data.session_reminders", false)
            ->assertJsonPath("data.promotions_updates", false);

        $this->assertDatabaseHas("notification_preferences", [
            "user_id" => $user->id,
            "session_reminders" => 0,
            "promotions_updates" => 0,
        ]);
    }

    public function test_unknown_key_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/user/notification-preferences", [
            "mystery_toggle" => true,
        ])->assertStatus(422)->assertJson(["success" => false]);
    }

    public function test_sms_channel_not_writable_via_v2(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        NotificationPreference::create(["user_id" => $user->id, "can_receive_sms" => 1]);

        $this->postJson("/api/v2/user/notification-preferences", [
            "can_receive_sms" => false,
        ])->assertStatus(422);

        $this->assertDatabaseHas("notification_preferences", [
            "user_id" => $user->id,
            "can_receive_sms" => 1,
        ]);
    }
}
