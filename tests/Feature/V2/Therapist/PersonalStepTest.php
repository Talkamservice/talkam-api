<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\TherapistApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_draft_application_with_credential_type_and_years(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 7,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapist_applications", [
            "user_id" => $user->id,
            "status" => "draft",
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 7,
        ]);
    }

    public function test_rejects_credential_type_outside_config_list(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Astrologer",
            "years_experience" => 3,
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertSame(0, TherapistApplication::count());
    }

    public function test_rejects_invalid_years_experience(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach ([-1, "many"] as $bad) {
            $this->postJson("/api/v2/therapist/application/personal", [
                "credential_type" => "Clinical Psychologist",
                "years_experience" => $bad,
            ])->assertStatus(422);
        }

        $this->assertSame(0, TherapistApplication::count());
    }

    public function test_session_rate_not_persisted_from_personal_step(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 5,
            "session_rate" => 18000,
        ])->assertStatus(200);

        $this->assertNull(TherapistApplication::where("user_id", $user->id)->first()->session_rate);
    }

    public function test_second_post_updates_same_draft_not_new_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 5,
        ]);
        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Psychiatrist",
            "years_experience" => 9,
        ])->assertStatus(200);

        $applications = TherapistApplication::where("user_id", $user->id)->get();
        $this->assertCount(1, $applications);
        $this->assertSame("Psychiatrist", $applications->first()->credential_type);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson("/api/v2/therapist/application/personal", [])->assertStatus(401);
    }
}
