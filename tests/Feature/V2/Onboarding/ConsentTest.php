<?php

namespace Tests\Feature\V2\Onboarding;

use App\Constants\Account\User\ConsentConstants;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private function allGranted(): array
    {
        return [
            ConsentConstants::ACCOUNT_OPERATION => true,
            ConsentConstants::SESSION_DELIVERY => true,
            ConsentConstants::ANONYMOUS_COMMUNITY => true,
            ConsentConstants::ANONYMISED_RESEARCH => true,
        ];
    }

    private function seedRequiredConsents(User $user): void
    {
        foreach (ConsentConstants::REQUIRED_KEYS as $key) {
            UserConsent::factory()->key($key)->create(["user_id" => $user->id]);
        }
    }

    public function test_confirm_with_required_consents_creates_rows_with_policy_version(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/consents", [
            "consents" => $this->allGranted(),
            "policy_version" => "2026-07",
        ])->assertStatus(200)->assertJson(["success" => true]);

        $this->assertSame(4, UserConsent::where("user_id", $user->id)->count());
        foreach (ConsentConstants::ALL_KEYS as $key) {
            $row = UserConsent::where(["user_id" => $user->id, "key" => $key])->first();
            $this->assertTrue($row->granted);
            $this->assertNotNull($row->granted_at);
            $this->assertSame("2026-07", $row->policy_version);
        }
    }

    public function test_confirm_rejects_required_consent_false(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $consents = $this->allGranted();
        $consents[ConsentConstants::ACCOUNT_OPERATION] = false;

        $this->postJson("/api/v2/user/consents", ["consents" => $consents])
            ->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame(0, UserConsent::where("user_id", $user->id)->count());
    }

    public function test_confirm_rejects_missing_required_consent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/consents", [
            "consents" => [ConsentConstants::ANONYMOUS_COMMUNITY => true],
        ])->assertStatus(400)->assertJson(["success" => false]);

        $this->assertSame(0, UserConsent::where("user_id", $user->id)->count());
    }

    public function test_required_consent_cannot_be_toggled_off(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedRequiredConsents($user);

        $this->postJson("/api/v2/user/consents", [
            "consents" => [ConsentConstants::ACCOUNT_OPERATION => false],
        ])->assertStatus(400)->assertJson(["success" => false]);

        $row = UserConsent::where([
            "user_id" => $user->id,
            "key" => ConsentConstants::ACCOUNT_OPERATION,
        ])->first();
        $this->assertTrue($row->granted);
        $this->assertNull($row->revoked_at);
    }

    public function test_optional_consent_toggles_freely(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedRequiredConsents($user);

        $key = ConsentConstants::ANONYMOUS_COMMUNITY;

        $this->postJson("/api/v2/user/consents", ["consents" => [$key => true]])
            ->assertStatus(200)->assertJsonPath("data.$key.granted", true);

        $this->postJson("/api/v2/user/consents", ["consents" => [$key => false]])
            ->assertStatus(200)->assertJsonPath("data.$key.granted", false);

        $this->postJson("/api/v2/user/consents", ["consents" => [$key => true]])
            ->assertStatus(200)->assertJsonPath("data.$key.granted", true);
    }

    public function test_research_revocation_sets_revoked_at(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedRequiredConsents($user);

        $key = ConsentConstants::ANONYMISED_RESEARCH;
        $granted_row = UserConsent::factory()->key($key)->create(["user_id" => $user->id]);
        $original_granted_at = $granted_row->granted_at;

        $this->postJson("/api/v2/user/consents", ["consents" => [$key => false]])
            ->assertStatus(200);

        $row = $granted_row->refresh();
        $this->assertFalse($row->granted);
        $this->assertNotNull($row->revoked_at);
        // Go-forward only: the original grant stays on the audit trail.
        $this->assertEquals($original_granted_at->toDateTimeString(), $row->granted_at->toDateTimeString());
    }

    public function test_repeat_post_updates_not_duplicates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/user/consents", ["consents" => $this->allGranted()])->assertStatus(200);
        $this->postJson("/api/v2/user/consents", ["consents" => $this->allGranted()])->assertStatus(200);

        foreach (ConsentConstants::ALL_KEYS as $key) {
            $this->assertSame(1, UserConsent::where(["user_id" => $user->id, "key" => $key])->count());
        }
    }

    public function test_get_returns_all_four_consent_states(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedRequiredConsents($user);

        $response = $this->getJson("/api/v2/user/consents")->assertStatus(200);

        $response->assertJsonPath("data." . ConsentConstants::ACCOUNT_OPERATION . ".granted", true)
            ->assertJsonPath("data." . ConsentConstants::SESSION_DELIVERY . ".granted", true)
            ->assertJsonPath("data." . ConsentConstants::ANONYMOUS_COMMUNITY . ".granted", false)
            ->assertJsonPath("data." . ConsentConstants::ANONYMISED_RESEARCH . ".granted", false);
    }

    public function test_consent_endpoints_require_authentication(): void
    {
        $this->getJson("/api/v2/user/consents")->assertStatus(401);
        $this->postJson("/api/v2/user/consents", ["consents" => $this->allGranted()])->assertStatus(401);
    }
}
