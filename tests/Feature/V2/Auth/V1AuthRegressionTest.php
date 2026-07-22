<?php

namespace Tests\Feature\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pins the v1 auth contract so the v2 lane can't silently change it.
 */
class V1AuthRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_register_still_issues_four_digit_pin(): void
    {
        Mail::fake();

        $this->postJson("/api/v1/auth/register", [
            "first_name" => "Ngozi",
            "last_name" => "Eze",
            "email" => "ngozi.eze@example.com",
            "password" => "any-old-password",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $user = User::where("email", "ngozi.eze@example.com")->first();
        $pin = Pin::where("user_id", $user->id)
            ->where("type", PinConstants::TYPE_VERIFY_EMAIL)
            ->first();

        $this->assertNotNull($pin);
        $this->assertSame(4, strlen((string) $pin->code));
    }

    public function test_v1_password_reset_accepts_code_and_password_without_confirmation(): void
    {
        $user = User::factory()->create();
        $pin = Pin::factory()->passwordReset()->create(["user_id" => $user->id]);

        // v1 contract: no confirmation field, no strength regex.
        $this->postJson("/api/v1/auth/password/reset", [
            "code" => $pin->code,
            "password" => "weakpass",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertTrue(Hash::check("weakpass", $user->refresh()->password));
    }

    public function test_v2_login_preview_route_is_not_registered(): void
    {
        $this->postJson("/api/v2/auth/login/preview", [
            "email" => "anyone@example.com",
        ])->assertStatus(404);
    }
}
