<?php

namespace Tests\Feature\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Mail\AppMailer;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_issues_six_digit_email_code(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson("/api/v2/auth/otp/request", [
            "email" => $user->email,
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $pin = Pin::where("user_id", $user->id)
            ->where("type", PinConstants::TYPE_VERIFY_EMAIL)
            ->first();

        $this->assertNotNull($pin);
        $this->assertSame(6, strlen((string) $pin->code));
        Mail::assertSent(AppMailer::class);
    }

    public function test_otp_verify_sets_email_verified_at(): void
    {
        $user = User::factory()->unverified()->create();
        $pin = Pin::factory()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/auth/otp/verify", [
            "code" => $pin->code,
            "email" => $user->email,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_otp_verify_rejects_wrong_code(): void
    {
        $user = User::factory()->unverified()->create();
        Pin::factory()->create(["user_id" => $user->id, "code" => "123456"]);

        $this->postJson("/api/v2/auth/otp/verify", [
            "code" => "654321",
            "email" => $user->email,
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_otp_verify_rejects_expired_code(): void
    {
        $user = User::factory()->unverified()->create();
        $pin = Pin::factory()->expired()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/auth/otp/verify", [
            "code" => $pin->code,
            "email" => $user->email,
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_otp_expiry_matches_config_value(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson("/api/v2/auth/otp/request", [
            "email" => $user->email,
            "type" => PinConstants::TYPE_VERIFY_EMAIL,
        ])->assertStatus(200);

        $pin = Pin::where("user_id", $user->id)->first();
        $expected = now()->addSeconds(config("system.configuration.pin_expiry"));

        $this->assertEqualsWithDelta(
            $expected->timestamp,
            \Carbon\Carbon::parse($pin->expires_at)->timestamp,
            5
        );
    }
}
