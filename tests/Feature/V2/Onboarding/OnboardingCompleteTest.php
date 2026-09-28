<?php

namespace Tests\Feature\V2\Onboarding;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_stamps_onboarding_completed_at(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/onboarding/complete")
            ->assertStatus(200)->assertJson(["success" => true]);

        $this->assertNotNull($user->refresh()->onboarding_completed_at);
    }

    public function test_complete_succeeds_without_avatar_set(): void
    {
        // Skip path: avatar selection is optional in onboarding.
        $user = User::factory()->create(["avatar" => null]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/onboarding/complete")->assertStatus(200);
        $this->assertNotNull($user->refresh()->onboarding_completed_at);
    }

    public function test_complete_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/onboarding/complete")->assertStatus(401);
    }
}
