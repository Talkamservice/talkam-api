<?php

namespace Tests\Feature\V2\Onboarding;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_avatar_list_returns_presets(): void
    {
        Avatar::factory()->count(4)->create();

        $this->getJson("/api/v2/profile/avatars")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonCount(4, "data");
    }

    public function test_profile_update_sets_avatar(): void
    {
        $user = User::factory()->create(["avatar" => null]);
        Sanctum::actingAs($user);
        $avatar = Avatar::factory()->create();

        $this->postJson("/api/v2/user/profile/update", [
            "avatar" => (string) $avatar->id,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertSame((string) $avatar->id, (string) $user->refresh()->avatar);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/profile/update", ["avatar" => "1"])->assertStatus(401);
    }
}
