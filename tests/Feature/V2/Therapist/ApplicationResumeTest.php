<?php

namespace Tests\Feature\V2\Therapist;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_status_and_per_step_completeness_in_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/therapist/application")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonPath("data.status", null)
            ->assertJsonPath("data.steps.personal", false)
            ->assertJsonPath("data.steps.documents", false)
            ->assertJsonPath("data.steps.specialties", false)
            ->assertJsonPath("data.steps.availability", false)
            ->assertJsonPath("data.steps.payout", false);
    }

    public function test_completeness_flags_reflect_saved_steps(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v2/therapist/application/personal", [
            "credential_type" => "Clinical Psychologist",
            "years_experience" => 5,
        ])->assertStatus(200);

        $this->getJson("/api/v2/therapist/application")
            ->assertJsonPath("data.status", "draft")
            ->assertJsonPath("data.steps.personal", true)
            ->assertJsonPath("data.steps.documents", false);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/therapist/application")->assertStatus(401);
    }
}
