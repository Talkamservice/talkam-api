<?php

namespace Tests\Feature\V2\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken("current");
        $other = $user->createToken("other-device");

        $this->withHeaders(["Authorization" => "Bearer {$current->plainTextToken}"])
            ->postJson("/api/v2/auth/logout")
            ->assertStatus(200)
            ->assertJson(["success" => true]);

        $this->assertDatabaseMissing("personal_access_tokens", ["id" => $current->accessToken->id]);
        $this->assertDatabaseHas("personal_access_tokens", ["id" => $other->accessToken->id]);
    }

    public function test_logout_with_all_devices_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken("current");
        $other = $user->createToken("other-device");

        $this->withHeaders(["Authorization" => "Bearer {$current->plainTextToken}"])
            ->postJson("/api/v2/auth/logout", ["all_devices" => true])
            ->assertStatus(200);

        $this->assertDatabaseMissing("personal_access_tokens", ["id" => $current->accessToken->id]);
        $this->assertDatabaseMissing("personal_access_tokens", ["id" => $other->accessToken->id]);
    }

    public function test_logout_without_a_token_is_unauthenticated(): void
    {
        $this->postJson("/api/v2/auth/logout")->assertStatus(401);
    }
}
