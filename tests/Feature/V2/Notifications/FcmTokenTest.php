<?php

namespace Tests\Feature\V2\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    private const URL = "/api/v2/user/notifications/fcm-token";

    public function test_it_updates_the_token_when_it_rotates(): void
    {
        $user = User::factory()->create(["fcm_token" => "old-token"]);
        Sanctum::actingAs($user);

        $this->postJson(self::URL, ["fcm_token" => "new-token"])->assertOk();

        $this->assertSame("new-token", $user->fresh()->fcm_token);
    }

    public function test_a_token_moves_off_the_previous_account_on_the_same_device(): void
    {
        $previous = User::factory()->create(["fcm_token" => "device-token"]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(self::URL, ["fcm_token" => "device-token"])->assertOk();

        $this->assertSame("device-token", $user->fresh()->fcm_token);
        $this->assertNull($previous->fresh()->fcm_token);
    }

    public function test_token_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(self::URL, [])->assertStatus(422);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson(self::URL, ["fcm_token" => "x"])->assertStatus(401);
    }
}
