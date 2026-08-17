<?php

namespace Tests\Feature\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Mail\AppMailer;
use App\Models\Pin;
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
 * submit) is unchanged and runs against a token obtained via /auth/login.
 *
 * The response itself carries no data — just message/success/code — since
 * the account isn't usable yet: the verify_email OTP sent here has to be
 * confirmed (POST /auth/otp/verify) and the client logs in separately
 * (POST /auth/login) to get a token.
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

    private function loginToken(string $email, string $password = "StrongP@ss1"): string
    {
        return $this->postJson("/api/v2/auth/login", [
            "input" => $email,
            "password" => $password,
        ])->json("data.token");
    }

    public function test_registers_user_and_starts_application_in_one_call(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())
            ->assertStatus(200)
            ->assertExactJson([
                "message" => "Therapist registered and onboarding started. An OTP has been sent to your email, check your email to verify.",
                "data" => [],
                "success" => true,
                "code" => 200,
            ]);

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertNotNull($user);

        $application = TherapistApplication::where("user_id", $user->id)->first();
        $this->assertNotNull($application);
        $this->assertSame("Licensed Therapist", $application->credential_type);
        $this->assertSame(5, $application->years_experience);
    }

    public function test_verify_email_otp_is_sent_and_flagged_in_the_response(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())
            ->assertStatus(200)
            ->assertJsonPath("message", "Therapist registered and onboarding started. An OTP has been sent to your email, check your email to verify.");

        $user = User::where("email", "danny.doe@example.com")->first();

        Mail::assertSent(AppMailer::class, function (AppMailer $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->subject === PinConstants::TITLES[PinConstants::TYPE_VERIFY_EMAIL];
        });

        $this->assertNotNull(Pin::where(["user_id" => $user->id, "type" => PinConstants::TYPE_VERIFY_EMAIL])->first());
    }

    public function test_login_after_registering_authenticates_the_next_onboarding_step(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())->assertStatus(200);
        $token = $this->loginToken("danny.doe@example.com");

        $this->assertNotEmpty($token);
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

        $this->postJson("/api/v2/auth/register-therapist", $payload)->assertStatus(200);

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertNotNull($user);
        $this->assertNull(TherapistApplication::where("user_id", $user->id)->first()->years_experience);

        $token = $this->loginToken("danny.doe@example.com");
        $this->withHeader("Authorization", "Bearer {$token}")
            ->getJson("/api/v2/therapist/application")
            ->assertJsonPath("data.steps.personal", true);
    }

    public function test_credential_type_is_optional_and_creates_bare_draft(): void
    {
        Mail::fake();
        $payload = $this->validPayload();
        unset($payload["credential_type"], $payload["years_experience"]);

        $this->postJson("/api/v2/auth/register-therapist", $payload)
            ->assertStatus(200)
            ->assertExactJson([
                "message" => "Therapist registered and onboarding started. An OTP has been sent to your email, check your email to verify.",
                "data" => [],
                "success" => true,
                "code" => 200,
            ]);

        $user = User::where("email", "danny.doe@example.com")->first();
        $this->assertNotNull($user);

        $application = TherapistApplication::where("user_id", $user->id)->first();
        $this->assertNotNull($application);
        $this->assertNull($application->credential_type);
        $this->assertSame("draft", $application->status);

        $token = $this->loginToken("danny.doe@example.com");
        $this->withHeader("Authorization", "Bearer {$token}")
            ->getJson("/api/v2/therapist/application")
            ->assertJsonPath("data.steps.personal", false);
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

    private function writeStepRoutes(): array
    {
        return [
            "POST /therapist/application/personal" => fn($token) => $this->withHeader("Authorization", "Bearer {$token}")
                ->postJson("/api/v2/therapist/application/personal", []),
            "POST /therapist/application/documents" => fn($token) => $this->withHeader("Authorization", "Bearer {$token}")
                ->postJson("/api/v2/therapist/application/documents", []),
            "POST /therapist/application/specialties" => fn($token) => $this->withHeader("Authorization", "Bearer {$token}")
                ->postJson("/api/v2/therapist/application/specialties", []),
            "POST /therapist/application/availability" => fn($token) => $this->withHeader("Authorization", "Bearer {$token}")
                ->postJson("/api/v2/therapist/application/availability", []),
            "POST /therapist/application/payout" => fn($token) => $this->withHeader("Authorization", "Bearer {$token}")
                ->postJson("/api/v2/therapist/application/payout", []),
            "POST /therapist/application/submit" => fn($token) => $this->withHeader("Authorization", "Bearer {$token}")
                ->postJson("/api/v2/therapist/application/submit", []),
        ];
    }

    public function test_write_steps_are_forbidden_until_email_is_verified(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())->assertStatus(200);
        $token = $this->loginToken("danny.doe@example.com");

        foreach ($this->writeStepRoutes() as $route => $call) {
            $call($token)
                ->assertStatus(403)
                ->assertJson(["success" => false])
                ->assertJsonPath("message", "Please verify your email before continuing.");
        }
    }

    public function test_reading_application_state_stays_open_when_email_is_unverified(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())->assertStatus(200);
        $token = $this->loginToken("danny.doe@example.com");

        $this->withHeader("Authorization", "Bearer {$token}")
            ->getJson("/api/v2/therapist/application")
            ->assertStatus(200);
    }

    public function test_write_steps_unlock_once_email_is_verified(): void
    {
        Mail::fake();

        $this->postJson("/api/v2/auth/register-therapist", $this->validPayload())->assertStatus(200);
        $token = $this->loginToken("danny.doe@example.com");

        $user = User::where("email", "danny.doe@example.com")->first();
        $pin = Pin::where(["user_id" => $user->id, "type" => PinConstants::TYPE_VERIFY_EMAIL])->first();
        $this->assertNotNull($pin);

        $this->postJson("/api/v2/auth/otp/verify", [
            "code" => $pin->code,
            "email" => $user->email,
        ])->assertStatus(200);

        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->withHeader("Authorization", "Bearer {$token}")
            ->postJson("/api/v2/therapist/application/personal", [
                "credential_type" => "Licensed Therapist",
                "years_experience" => 6,
            ])->assertStatus(200)->assertJson(["success" => true]);
    }
}
