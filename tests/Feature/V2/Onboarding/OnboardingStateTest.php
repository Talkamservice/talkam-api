<?php

namespace Tests\Feature\V2\Onboarding;

use App\Constants\Account\User\ConsentConstants;
use App\Models\PostCategory;
use App\Models\TherapistApplication;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\UserInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_exposes_derived_onboarding_object(): void
    {
        $user = User::factory()->create(["avatar" => null]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/me")
            ->assertStatus(200)
            ->assertJsonPath("data.onboarding.interests", false)
            ->assertJsonPath("data.onboarding.avatar", false)
            ->assertJsonPath("data.onboarding.consents", false)
            ->assertJsonPath("data.onboarding.completed_at", null);
    }

    public function test_interests_step_flips_at_three_interests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $topics = PostCategory::factory()->count(3)->interestTopic()->create();

        foreach ($topics->take(2) as $topic) {
            UserInterest::create(["user_id" => $user->id, "category_id" => $topic->id]);
        }
        $this->getJson("/api/v2/user/me")->assertJsonPath("data.onboarding.interests", false);

        UserInterest::create(["user_id" => $user->id, "category_id" => $topics->last()->id]);
        $this->getJson("/api/v2/user/me")->assertJsonPath("data.onboarding.interests", true);
    }

    public function test_avatar_and_consent_steps_derive_from_state(): void
    {
        $user = User::factory()->create(["avatar" => null]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/me")
            ->assertJsonPath("data.onboarding.avatar", false)
            ->assertJsonPath("data.onboarding.consents", false);

        $user->update(["avatar" => "3"]);
        foreach (ConsentConstants::REQUIRED_KEYS as $key) {
            UserConsent::factory()->key($key)->create(["user_id" => $user->id]);
        }

        $this->getJson("/api/v2/user/me")
            ->assertJsonPath("data.onboarding.avatar", true)
            ->assertJsonPath("data.onboarding.consents", true);
    }

    public function test_me_returns_therapist_role_for_in_progress_applicants(): void
    {
        // Create a user with role "User" but who has a therapist application
        $user = User::factory()->create(["role" => "User"]);
        TherapistApplication::factory()->create([
            "user_id" => $user->id,
            "status" => "draft",
            "credential_type" => "Licensed Therapist",
        ]);

        Sanctum::actingAs($user);

        // The users.role column is still "User" in the database
        $this->assertSame("User", $user->role);

        // But the /user/me endpoint should return "Therapist" because
        // the user has an in-progress application
        $this->getJson("/api/v2/user/me")
            ->assertStatus(200)
            ->assertJsonPath("data.role", "Therapist");
    }

    public function test_me_returns_user_role_for_regular_users(): void
    {
        $user = User::factory()->create(["role" => "User"]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/me")
            ->assertStatus(200)
            ->assertJsonPath("data.role", "User");
    }
}
