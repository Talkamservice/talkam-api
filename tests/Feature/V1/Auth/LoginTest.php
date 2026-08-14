<?php

namespace Tests\Feature\V1\Auth;

use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V1 login is what the consumer/mobile-web app actually calls. It used to
 * return no role context at all, so a therapist and a regular client were
 * indistinguishable to the client app after login. This adds the same
 * `business` context v2's login already sends, so a therapist can be
 * routed straight into therapist mode instead of the generic user home.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_envelope(): void
    {
        $user = User::factory()->create();

        $this->postJson("/api/v1/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200)
            ->assertJson(["success" => true])
            ->assertJsonStructure(["message", "data" => ["token", "user", "business"], "success", "code"]);
    }

    public function test_plain_user_business_context_reports_not_a_therapist(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/v1/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200);

        $this->assertFalse($response->json("data.business.is_therapist"));
        $this->assertNull($response->json("data.business.dashboard"));
    }

    public function test_therapist_business_context_points_at_therapist_dashboard(): void
    {
        $therapist = Therapist::factory()->create();
        $user = $therapist->user;

        $response = $this->postJson("/api/v1/auth/login", [
            "input" => $user->email,
            "password" => "password",
        ])->assertStatus(200);

        $this->assertTrue($response->json("data.business.is_therapist"));
        $this->assertSame("therapist", $response->json("data.business.dashboard"));
    }
}
