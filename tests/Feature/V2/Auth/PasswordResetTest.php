<?php

namespace Tests\Feature\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Mail\AppMailer;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_creates_six_digit_reset_pin_and_mails_it(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson("/api/v2/auth/password/forgot", ["email" => $user->email])
            ->assertStatus(200)
            ->assertJson(["success" => true]);

        $pin = Pin::where("user_id", $user->id)
            ->where("type", PinConstants::TYPE_PASSWORD_RESET)
            ->first();

        $this->assertNotNull($pin);
        $this->assertSame(6, strlen((string) $pin->code));
        Mail::assertSent(AppMailer::class);
    }

    public function test_forgot_is_throttled(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/v2/auth/password/forgot", ["email" => $user->email]);
        }

        $this->postJson("/api/v2/auth/password/forgot", ["email" => $user->email])
            ->assertStatus(429);
    }

    public function test_reset_with_valid_code_and_confirmation_changes_password(): void
    {
        $user = User::factory()->create();
        $pin = Pin::factory()->passwordReset()->create(["user_id" => $user->id]);

        $this->postJson("/api/v2/auth/password/reset", [
            "code" => $pin->code,
            "password" => "NewStr0ng@Pass",
            "password_confirmation" => "NewStr0ng@Pass",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertTrue(Hash::check("NewStr0ng@Pass", $user->refresh()->password));

        // The PIN must not be reusable after a successful reset.
        $this->postJson("/api/v2/auth/password/reset", [
            "code" => $pin->code,
            "password" => "An0ther@Pass1",
            "password_confirmation" => "An0ther@Pass1",
        ])->assertStatus(400)->assertJson(["success" => false]);
    }

    public function test_reset_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $pin = Pin::factory()->passwordReset()->create(["user_id" => $user->id]);
        $old_hash = $user->password;

        $this->postJson("/api/v2/auth/password/reset", [
            "code" => $pin->code,
            "password" => "NewStr0ng@Pass",
            "password_confirmation" => "DifferentP@ss1",
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame($old_hash, $user->refresh()->password);
    }

    public function test_reset_rejects_weak_password(): void
    {
        $user = User::factory()->create();
        $pin = Pin::factory()->passwordReset()->create(["user_id" => $user->id]);
        $old_hash = $user->password;

        $this->postJson("/api/v2/auth/password/reset", [
            "code" => $pin->code,
            "password" => "weakpass",
            "password_confirmation" => "weakpass",
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame($old_hash, $user->refresh()->password);
    }

    public function test_reset_rejects_same_as_current_password(): void
    {
        $user = User::factory()->create([
            "password" => Hash::make("Curr3nt@Pass"),
        ]);
        $pin = Pin::factory()->passwordReset()->create(["user_id" => $user->id]);
        $old_hash = $user->password;

        $this->postJson("/api/v2/auth/password/reset", [
            "code" => $pin->code,
            "password" => "Curr3nt@Pass",
            "password_confirmation" => "Curr3nt@Pass",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame($old_hash, $user->refresh()->password);
    }

    public function test_reset_rejects_expired_or_unknown_code(): void
    {
        $user = User::factory()->create();
        $expired_pin = Pin::factory()->passwordReset()->expired()->create(["user_id" => $user->id]);
        $old_hash = $user->password;

        $this->postJson("/api/v2/auth/password/reset", [
            "code" => $expired_pin->code,
            "password" => "NewStr0ng@Pass",
            "password_confirmation" => "NewStr0ng@Pass",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->postJson("/api/v2/auth/password/reset", [
            "code" => "000000",
            "password" => "NewStr0ng@Pass",
            "password_confirmation" => "NewStr0ng@Pass",
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame($old_hash, $user->refresh()->password);
    }
}
