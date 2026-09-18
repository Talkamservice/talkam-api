<?php

namespace Tests\Feature\V2\TherapistProfile;

use App\Models\Therapist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TherapistProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_bio_experience_and_rate_within_caps(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/profile/update", [
            "bio" => "CBT-focused practice.",
            "years_experience" => 9,
            "session_rate" => config("therapist.session_rate.min"),
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("therapists", [
            "id" => $therapist->id,
            "years_experience" => 9,
        ]);
        $this->assertSame("CBT-focused practice.", $therapist->user->refresh()->bio);
    }

    public function test_bio_over_300_chars_is_rejected(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/profile/update", [
            "bio" => str_repeat("a", 301),
        ])->assertStatus(422);
    }

    public function test_session_rate_above_config_cap_is_rejected(): void
    {
        $therapist = Therapist::factory()->create(["session_rate" => 15000]);
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/profile/update", [
            "session_rate" => config("therapist.session_rate.max") + 1,
        ])->assertStatus(422);

        $this->assertEquals(15000, (float) $therapist->refresh()->session_rate);
    }

    public function test_credential_type_is_not_persisted(): void
    {
        $therapist = Therapist::factory()->create(["credential_type" => "Clinical Psychologist"]);
        Sanctum::actingAs($therapist->user);

        $this->postJson("/api/v2/therapist/profile/update", [
            "credential_type" => "Psychiatrist",
            "bio" => "hello",
        ])->assertStatus(200);

        $this->assertDatabaseHas("therapists", [
            "id" => $therapist->id,
            "credential_type" => "Clinical Psychologist",
        ]);
    }

    public function test_profile_self_view_returns_section_07_shape(): void
    {
        $therapist = Therapist::factory()->create();
        Sanctum::actingAs($therapist->user);

        $this->getJson("/api/v2/therapist/profile")
            ->assertStatus(200)
            ->assertJsonStructure(["data" => [
                "id", "name", "is_verified", "credential_type", "session_rate",
                "bio", "specialties", "session_formats", "completed_sessions", "ratings_histogram",
            ]]);
    }

    public function test_plain_user_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v2/therapist/profile/update", [])->assertStatus(403);
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson("/api/v2/therapist/profile/update", [])->assertStatus(401);
    }
}
