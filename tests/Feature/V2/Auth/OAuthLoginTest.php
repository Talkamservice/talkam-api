<?php

namespace Tests\Feature\V2\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class OAuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_login_with_invalid_token_fails(): void
    {
        Socialite::shouldReceive("driver->userFromToken")
            ->andThrow(new \Exception("The token has expired or is invalid."));

        $this->postJson("/api/v2/auth/oauth-login", [
            "token" => "bogus-token",
            "provider" => "google",
        ])->assertJson(["success" => false]);

        $this->assertSame(0, User::count());
    }

    public function test_oauth_login_creates_user_for_new_google_account(): void
    {
        $socialite_user = new SocialiteUser;
        $socialite_user->email = "new.google.user@example.com";
        $socialite_user->user = ["given_name" => "Ada", "family_name" => "Obi"];

        Socialite::shouldReceive("driver->userFromToken")
            ->andReturn($socialite_user);

        $response = $this->postJson("/api/v2/auth/oauth-login", [
            "token" => "valid-google-token",
            "provider" => "google",
        ]);

        $response->assertStatus(200)
            ->assertJson(["success" => true])
            ->assertJsonStructure(["message", "data" => ["token", "user"], "success", "code"]);

        $this->assertDatabaseHas("users", [
            "email" => "new.google.user@example.com",
            "first_name" => "Ada",
        ]);
    }
}
