<?php

namespace Tests\Feature\V2\Auth;

use App\Models\TherapistApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * POST /auth/register-therapist — registration + the application's
 * "personal" step in one unauthenticated call, so a prospective therapist
 * never has to exist as a plain logged-in user first before onboarding
 * can start. The rest of the wizard (documents/specialties/availability/
 * submit) is unchanged and runs against the token returned here.
 */
class RegisterTherapistTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            "full_name" => "Danny Doe",
            "email" => "danny.doe@example.com",
            "phone_number" => "+2348012345678",
            "username" => "danny_doe",
            "password" => "StrongP@ss1",
            "password_confirmation" => "StrongP@ss1",
            "credential_type" => "Licensed Therapist",
            "years_experience" => 5,
        ], $overrides);
    }

    public function test_registers_user_and_starts_application_in_one_call(): void
    {
        Mail::fake();

        $response = $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())
            ->assertStatus(200)
            ->assertJsonStructure([
                "message",
                "data" => ["token", "user", "application" => ["application_id", "status", "steps"]],
                "success",
                "code",
            ]);

        $this->assertNotEmpty($response->json("data.token"));
        $this->assertTrue($response->json("data.application.steps.personal"));

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertNotNull($user);

        $application = TherapistApplication::where("user_id", $user->id)->first();
        $this->assertNotNull($application);
        $this->assertSame("Licensed Therapist", $application->credential_type);
        $this->assertSame(5, $application->years_experience);
    }

    public function test_returned_token_authenticates_the_next_onboarding_step(): void
    {
        Mail::fake();

        $token = $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())
            ->json("data.token");

        $this->withHeader("Authorization", "Bearer {$token}")
            ->getJson("/api/v2/therapist/application")
            ->assertStatus(200)
            ->assertJsonPath("data.steps.personal", true);
    }

    public function test_username_is_optional_and_generated_when_omitted(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["username"]);

        $this->postJson("/api/v2/auth/register-therapist", $payload)->assertStatus(200);

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->username);
    }

    public function test_own_username_is_still_respected_when_sent(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())->assertStatus(200);

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertSame("danny_doe", $user->username);
    }

    public function test_years_experience_is_optional(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["years_experience"]);

        $response = $this->postJson("/api/v2/auth/register-therapist", $payload)->assertStatus(200);

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertNotNull($user);
        $this->assertNull(TherapistApplication::where("user_id", $user->id)->first()->years_experience);
        $this->assertTrue($response->json("data.application.steps.personal"));
    }

    public function test_missing_credential_type_creates_no_user(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["credential_type"]);

        $this->postJson("/api/v2/auth/register-therapist", $payload)
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "danny.doe@example.com"]);
    }

    public function test_invalid_credential_type_creates_no_user(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload([
            "credential_type" => "Not A Real Credential",
        ]))->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["email" => "danny.doe@example.com"]);
    }

    public function test_mismatched_password_confirmation_creates_no_user(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload([
            "password_confirmation" => "SomethingElse1!",
        ]))->assertStatus(422)->assertJson(["success" => false])
            ->assertJsonValidationErrors(["password"]);

        $this->assertDatabaseMissing("users", ["email" => "danny.doe@example.com"]);
    }

    public function test_missing_password_confirmation_creates_no_user(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["password_confirmation"]);

        $this->postJson("/api/v2/auth/register-therapist", $payload)
            ->assertStatus(422)->assertJson(["success" => false])
            ->assertJsonValidationErrors(["password"]);

        $this->assertDatabaseMissing("users", ["email" => "danny.doe@example.com"]);
    }

    public function test_duplicate_email_creates_no_application(): void
    {
        Mail::fake();
        User::factory()->create(["email" => "danny.doe@example.com"]);

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload(["username" => "other_name"]))
            ->assertStatus(422)->assertJson(["success" => false]);

        $this->assertDatabaseMissing("users", ["username" => "other_name"]);
        $this->assertDatabaseCount("therapist_applications", 0);
    }
}
