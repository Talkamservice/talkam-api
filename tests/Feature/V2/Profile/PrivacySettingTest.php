<?php

namespace Tests\Feature\V2\Profile;

use App\Models\Pin;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_defaults_without_row(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/privacy-settings")
            ->assertStatus(200)
            ->assertJsonPath("data.anonymous_mode", false)
            ->assertJsonPath("data.read_receipts", true)
            ->assertJsonPath("data.activity_status", true)
            ->assertJsonPath("data.two_factor_enabled", false);
    }

    public function test_toggles_persist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/privacy-settings", [
            "anonymous_mode" => true,
            "read_receipts" => false,
            "activity_status" => false,
        ])->assertStatus(200)
            ->assertJsonPath("data.anonymous_mode", true)
            ->assertJsonPath("data.read_receipts", false)
            ->assertJsonPath("data.activity_status", false);

        $this->assertDatabaseHas("user_privacy_settings", [
            "user_id" => $user->id,
            "anonymous_mode" => 1,
            "read_receipts" => 0,
        ]);
    }

    public function test_anonymous_mode_does_not_touch_consents(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        UserConsent::factory()->key("anonymous_community")->create([
            "user_id" => $user->id,
            "granted" => false,
            "granted_at" => null,
        ]);

        $this->postJson("/api/v2/user/privacy-settings", ["anonymous_mode" => true])
            ->assertStatus(200);

        $this->assertDatabaseHas("user_consents", [
            "user_id" => $user->id,
            "key" => "anonymous_community",
            "granted" => 0,
        ]);
    }

    public function test_enabling_2fa_requires_fresh_otp(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/privacy-settings", ["two_factor_enabled" => true])
            ->assertStatus(400)->assertJson(["success" => false]);

        $pin = Pin::factory()->create([
            "user_id" => $user->id,
            "type" => "login",
            "code" => "246810",
        ]);

        $this->postJson("/api/v2/user/privacy-settings", [
            "two_factor_enabled" => true,
            "otp" => "246810",
        ])->assertStatus(200)->assertJsonPath("data.two_factor_enabled", true);
    }
}
