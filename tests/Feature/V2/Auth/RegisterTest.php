<?php

namespace Tests\Feature\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Mail\AppMailer;
use App\Models\Country;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            "full_name" => "Ada Chioma Obi",
            "email" => "ada.obi@example.com",
            "phone_number" => "+2348012345678",
            "username" => "ada_obi",
            "password" => "StrongP@ss1",
        ], $overrides);
    }

    public function test_register_succeeds_with_full_name_split(): void
    {
        Mail::fake();

        $response = $this->postJson("/api/v2/auth/register", $this->validPayload());

        $response->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["message", "data" => ["token", "user"], "success", "code"]);

        $this->assertDatabaseHas("users", [
            "email" => "ada.obi@example.com",
            "first_name" => "Ada",
            "middle_name" => "Chioma",
            "last_name" => "Obi",
            "username" => "ada_obi",
            "phone_number" => "+2348012345678",
        ]);
    }

    public function test_register_stores_valid_country_id(): void
    {
        Mail::fake();
        $country = Country::factory()->create();

        $this->postJson("/api/v2/auth/register", $this->validPayload([
            "country_id" => $country->id,
        ]))->assertStatus(200);

        $this->assertDatabaseHas("users", [
            "email" => "ada.obi@example.com",
            "country_id" => $country->id,
        ]);
    }

    public function test_register_requires_valid_country_id(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register", $this->validPayload([
            "country_id" => 999999,
        ]))->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "ada.obi@example.com"]);
    }

    public function test_register_requires_phone_number(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["phone_number"]);

        $this->postJson("/api/v2/auth/register", $payload)
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "ada.obi@example.com"]);
    }

    public function test_register_requires_unique_username(): void
    {
        Mail::fake();
        User::factory()->create(["username" => "ada_obi"]);

        $this->postJson("/api/v2/auth/register", $this->validPayload())
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "ada.obi@example.com"]);
    }

    public function test_register_requires_username(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["username"]);

        $this->postJson("/api/v2/auth/register", $payload)
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "ada.obi@example.com"]);
    }

    public function test_register_requires_unique_email(): void
    {
        Mail::fake();
        User::factory()->create(["email" => "ada.obi@example.com"]);

        $this->postJson("/api/v2/auth/register", $this->validPayload(["username" => "other_name"]))
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["username" => "other_name"]);
    }

    public function test_register_rejects_password_failing_regex(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register", $this->validPayload([
            "password" => "password1",
        ]))->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "ada.obi@example.com"]);
    }

    public function test_register_creates_six_digit_email_pin(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register", $this->validPayload())->assertStatus(200);

        $user = User::where("email", "ada.obi@example.com")->first();
        $pin = Pin::where("user_id", $user->id)->where("type", PinConstants::TYPE_VERIFY_EMAIL)->first();

        $this->assertNotNull($pin);
        $this->assertSame(6, strlen((string) $pin->code));
        Mail::assertSent(AppMailer::class);
    }
}
