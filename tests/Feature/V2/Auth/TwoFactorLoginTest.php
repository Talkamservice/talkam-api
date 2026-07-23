<?php

namespace Tests\Feature\V2\Auth;

use App\Models\Pin;
use App\Models\User;
use App\Models\UserPrivacySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private function twoFactorUser(): User
    {
        $user = User::factory()->create();
        UserPrivacySetting::create([
            "user_id" => $user->id,
            "two_factor_enabled" => true,
        ]);

        return $user;
    }

    public function test_enabled_user_login_returns_challenge_and_issues_login_otp(): void
    {
        Mail::fake();
        $user = $this->twoFactorUser();

        $response = $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200)
            ->assertJsonPath("data.two_factor_required", true);

        $this->assertNull($response->json("data.token"));
        $this->assertDatabaseHas("pins", ["user_id" => $user->id, "type" => "login"]);
        $this->assertSame(6, strlen((string) Pin::where("user_id", $user->id)->first()->code));
    }

    public function test_verify_valid_otp_mints_token(): void
    {
        Mail::fake();
        $user = $this->twoFactorUser();
        $this->postJson("/api/v2/auth/login", ["input" => $user->email, "password" => "password"]);
        $code = Pin::where("user_id", $user->id)->where("type", "login")->first()->code;

        $response = $this->postJson("/api/v2/auth/2fa/verify", [
            "email" => $user->email,
            "code" => (string) $code,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertNotEmpty($response->json("data.token"));
    }

    public function test_invalid_or_expired_otp_rejected(): void
    {
        Mail::fake();
        $user = $this->twoFactorUser();
        $this->postJson("/api/v2/auth/login", ["input" => $user->email, "password" => "password"]);

        $this->postJson("/api/v2/auth/2fa/verify", [
            "email" => $user->email,
            "code" => "000000",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $code = Pin::where("user_id", $user->id)->where("type", "login")->first()->code;
        $this->travel((int) ceil(config("system.configuration.pin_expiry") / 60) + 1)->minutes();

        $this->postJson("/api/v2/auth/2fa/verify", [
            "email" => $user->email,
            "code" => (string) $code,
        ])->assertStatus(400);
    }

    public function test_disabled_user_login_returns_token_directly(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200);

        $this->assertNotEmpty($response->json("data.token"));
    }

    public function test_verify_without_challenge_rejected(): void
    {
        $user = $this->twoFactorUser();

        $this->postJson("/api/v2/auth/2fa/verify", [
            "email" => $user->email,
            "code" => "123456",
        ])->assertStatus(400)->assertJson(["success" => false]);
    }
}
