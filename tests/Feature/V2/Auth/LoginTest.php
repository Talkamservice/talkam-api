<?php

namespace Tests\Feature\V2\Auth;

use App\Models\TherapistApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_email_returns_token_envelope(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ]);

        $response->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["message", "data" => ["token", "user"], "success", "code"]);
    }

    public function test_login_reflects_therapist_role_for_an_active_applicant(): void
    {
        $user = User::factory()->create();
        TherapistApplication::factory()->for($user)->create();

        $this->assertSame("User", $user->role);

        $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200)->assertJsonPath("data.user.role", "Therapist");
    }

    public function test_login_role_stays_user_without_a_therapist_application(): void
    {
        $user = User::factory()->create();

        $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200)->assertJsonPath("data.user.role", "User");
    }

    public function test_login_with_username_input_succeeds(): void
    {
        $user = User::factory()->create(["username" => "chidi_okafor"]);

        $this->postJson("/api/v2/auth/login", [
            "input" => "chidi_okafor",
            "password" => "password",
        ])->assertStatus(200)->assertJson(["success" => true]);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "wrong-password",
        ]);

        $response->assertStatus(400)->assertJson(["success" => false]);
        $this->assertArrayNotHasKey("token", $response->json("data") ?? []);
    }

    public function test_banned_user_cannot_login(): void
    {
        Mail::fake();
        $user = User::factory()->banned()->create();

        $this->postJson("/api/v2/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(400)->assertJson(["success" => false]);
    }
}
