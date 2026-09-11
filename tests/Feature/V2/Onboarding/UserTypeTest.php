<?php

namespace Tests\Feature\V2\Onboarding;

use App\Constants\Account\User\OnboardingConstants;
use App\Constants\Account\User\UserConstants;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_type_is_stored(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/onboarding/user-type", [
            "user_type" => OnboardingConstants::USER_TYPE_SUPPORT_SEEKER,
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertDatabaseHas("users", [
            "id" => $user->id,
            "user_type" => "support_seeker",
        ]);
    }

    public function test_invalid_user_type_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/onboarding/user-type", [
            "user_type" => "therapist",
        ])->assertStatus(422)->assertJson(["success" => false]);

        $this->assertNull($user->refresh()->user_type);
    }

    public function test_role_is_never_changed_by_user_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/onboarding/user-type", [
            "user_type" => OnboardingConstants::USER_TYPE_MENTAL_HEALTH_PRO,
        ])->assertStatus(200);

        $this->assertSame(UserConstants::USER, $user->refresh()->role);
    }

    public function test_user_type_requires_authentication(): void
    {
        $this->postJson("/api/v2/user/onboarding/user-type", [
            "user_type" => "support_seeker",
        ])->assertStatus(401);
    }
}
