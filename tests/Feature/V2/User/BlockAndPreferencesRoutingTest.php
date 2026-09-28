<?php

namespace Tests\Feature\V2\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockAndPreferencesRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_add_and_preferences_respond_under_v2(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/blocked-users/add", ["blocked_user_id" => $target->id])
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->getJson("/api/v2/user/notification-preferences")
            ->assertStatus(200)->assertJson(["success" => true, "code" => 200]);
    }

    public function test_v1_routes_unchanged(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // v1 notification preference fetch + block list still resolve.
        $this->getJson("/api/v1/user/notifications/preference/fetch")->assertStatus(200);
        $this->getJson("/api/v1/user/blocked-users")->assertStatus(200);
    }
}
