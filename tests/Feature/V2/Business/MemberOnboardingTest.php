<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Account\User\ConsentConstants;
use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Models\EmployeeSelfCheck;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PostCategory;
use App\Models\User;
use Database\Seeders\InterestTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = OrganizationConstants::ROLE_EMPLOYEE): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $membership = OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
            "role" => $role,
        ]);

        Sanctum::actingAs($user);

        return [$organization, $user, $membership];
    }

    /* ── Consent (reuse of the §02 endpoint through the web keys) ───────── */

    public function test_consent_reuses_the_existing_endpoint_and_still_enforces_required_keys(): void
    {
        [, $user] = $this->member();

        // Required keys missing → rejected, nothing stored.
        $this->postJson("/api/v2/user/consents", [
            "consents" => [
                ConsentConstants::ACCOUNT_OPERATION => false,
                ConsentConstants::SESSION_DELIVERY => true,
                ConsentConstants::ANONYMOUS_COMMUNITY => true,
                ConsentConstants::ANONYMISED_RESEARCH => false,
            ],
        ])->assertStatus(400);

        $this->assertSame(0, $user->consents()->count());

        $this->postJson("/api/v2/user/consents", [
            "consents" => [
                ConsentConstants::ACCOUNT_OPERATION => true,
                ConsentConstants::SESSION_DELIVERY => true,
                ConsentConstants::ANONYMOUS_COMMUNITY => false,
                ConsentConstants::ANONYMISED_RESEARCH => false,
            ],
            "policy_version" => "2026-07",
        ])->assertStatus(200);

        $this->assertSame(4, $user->consents()->count());
    }

    public function test_consent_state_carries_the_required_flag_the_screen_renders_as_a_tag(): void
    {
        $this->member();

        $this->getJson("/api/v2/user/consents")
            ->assertStatus(200)
            ->assertJsonPath("data." . ConsentConstants::ACCOUNT_OPERATION . ".required", true)
            ->assertJsonPath("data." . ConsentConstants::ANONYMOUS_COMMUNITY . ".required", false)
            ->assertJsonPath("data." . ConsentConstants::ACCOUNT_OPERATION . ".granted", false);
    }

    /* ── Topics ─────────────────────────────────────────────────────────── */

    public function test_topic_options_return_the_six_deck_chips_in_order(): void
    {
        $this->member();
        (new InterestTopicSeeder)->run();

        $response = $this->getJson("/api/v2/business/onboarding/topics")->assertStatus(200);

        $topics = $response->json("data.topics");
        $this->assertCount(6, $topics);
        $this->assertSame(
            ["Anxiety", "Depression", "Relationships", "Work Stress", "Grief", "General Support"],
            array_column($topics, "label")
        );
        $this->assertSame(
            ["anxiety", "depression", "relationships", "work", "grief", "general"],
            array_column($topics, "key")
        );

        foreach ($topics as $topic) {
            $this->assertNotEmpty($topic["id"]);
        }
    }

    public function test_topics_accept_a_single_selection(): void
    {
        [, $user] = $this->member();
        (new InterestTopicSeeder)->run();

        $anxiety = PostCategory::where("name", "Anxiety")
            ->where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->first();

        $this->postJson("/api/v2/business/onboarding/topics", ["interests" => [$anxiety->id]])
            ->assertStatus(200);

        $this->assertSame(1, $user->interests()->count());
        $this->assertSame($anxiety->id, $user->interests()->first()->category_id);
    }

    public function test_topics_reject_an_empty_selection(): void
    {
        [, $user] = $this->member();

        $this->postJson("/api/v2/business/onboarding/topics", ["interests" => []])
            ->assertStatus(422);

        $this->assertSame(0, $user->interests()->count());
    }

    public function test_topics_reject_a_category_that_is_not_an_interest_topic(): void
    {
        [, $user] = $this->member();

        $ordinary = PostCategory::create([
            "name" => "Not A Topic",
            "status" => StatusConstants::ACTIVE,
        ]);

        $this->postJson("/api/v2/business/onboarding/topics", ["interests" => [$ordinary->id]])
            ->assertStatus(400);

        $this->assertSame(0, $user->interests()->count());
    }

    /** The mobile contract is untouched: user/profile/interests still demands 3. */
    public function test_the_mobile_interest_endpoint_still_requires_three(): void
    {
        $this->member();
        (new InterestTopicSeeder)->run();

        $anxiety = PostCategory::where("name", "Anxiety")->first();

        $this->postJson("/api/v2/user/profile/interests", ["interests" => [$anxiety->id]])
            ->assertStatus(422);
    }

    /* ── Self check-in ──────────────────────────────────────────────────── */

    public function test_self_check_stores_four_rows_and_returns_the_primary_concern(): void
    {
        [$organization, $user] = $this->member();

        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 3, "anxiety" => 1, "sleep" => 0, "relationships" => 2],
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.primary_category", "work")
            ->assertJsonPath("data.primary_concern", "Work Stress")
            ->assertJsonPath("data.completed", true);

        $rows = EmployeeSelfCheck::where("user_id", $user->id)->get();
        $this->assertCount(4, $rows);
        $this->assertSame($organization->id, $rows->first()->organization_id);
        $this->assertSame(3, $rows->firstWhere("category", "work")->score);
    }

    public function test_retaking_the_self_check_overwrites_rather_than_duplicates(): void
    {
        [, $user] = $this->member();

        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 3, "anxiety" => 1, "sleep" => 0, "relationships" => 0],
        ])->assertStatus(200);

        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 0, "anxiety" => 3, "sleep" => 1, "relationships" => 0],
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.primary_category", "anxiety")
            ->assertJsonPath("data.primary_concern", "Anxiety");

        $this->assertSame(4, EmployeeSelfCheck::where("user_id", $user->id)->count());
        $this->assertSame(
            0,
            EmployeeSelfCheck::where(["user_id" => $user->id, "category" => "work"])->first()->score
        );
    }

    public function test_self_check_rejects_a_missing_category_or_an_out_of_range_score(): void
    {
        [, $user] = $this->member();

        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 3, "anxiety" => 1, "sleep" => 0],
        ])->assertStatus(422);

        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 9, "anxiety" => 1, "sleep" => 0, "relationships" => 0],
        ])->assertStatus(422);

        $this->assertSame(0, EmployeeSelfCheck::where("user_id", $user->id)->count());
    }

    public function test_self_check_state_carries_the_questionnaire_the_screen_renders(): void
    {
        $this->member();

        $this->getJson("/api/v2/business/self-check")
            ->assertStatus(200)
            ->assertJsonCount(4, "data.questions")
            ->assertJsonCount(4, "data.options")
            ->assertJsonPath("data.questions.0.label", "Work-related stress")
            ->assertJsonPath("data.options.3.label", "Significant")
            ->assertJsonPath("data.completed", false);
    }

    public function test_an_admin_cannot_take_the_employee_self_check(): void
    {
        $this->member(OrganizationConstants::ROLE_ADMIN);

        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 3, "anxiety" => 1, "sleep" => 0, "relationships" => 0],
        ])->assertStatus(403);

        $this->assertSame(0, EmployeeSelfCheck::count());
    }

    /* ── Routing context ────────────────────────────────────────────────── */

    public function test_user_me_exposes_the_business_context_the_router_reads(): void
    {
        [$organization, $user] = $this->member();
        (new InterestTopicSeeder)->run();

        $this->getJson("/api/v2/user/me")
            ->assertStatus(200)
            ->assertJsonPath("data.business.is_member", true)
            ->assertJsonPath("data.business.role", "employee")
            ->assertJsonPath("data.business.dashboard", "employee")
            ->assertJsonPath("data.business.organization.id", $organization->id)
            ->assertJsonPath("data.business.onboarding.consent", false)
            ->assertJsonPath("data.business.onboarding.topics", false)
            ->assertJsonPath("data.business.onboarding.self_check", false);

        $anxiety = PostCategory::where("name", "Anxiety")->first();
        $this->postJson("/api/v2/business/onboarding/topics", ["interests" => [$anxiety->id]])->assertStatus(200);
        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 1, "anxiety" => 1, "sleep" => 1, "relationships" => 1],
        ])->assertStatus(200);

        $this->getJson("/api/v2/user/me")
            ->assertJsonPath("data.business.onboarding.topics", true)
            ->assertJsonPath("data.business.onboarding.self_check", true);
    }

    public function test_a_non_member_reports_is_member_false(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/me")
            ->assertStatus(200)
            ->assertJsonPath("data.business.is_member", false)
            ->assertJsonPath("data.business.role", null)
            ->assertJsonPath("data.business.dashboard", null);
    }

    public function test_login_returns_the_dashboard_to_land_on(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(["email" => "adaeze@zenithbank.com"]);

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        $this->postJson("/api/v2/auth/login", [
            "input" => "adaeze@zenithbank.com",
            "password" => "password",
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.business.dashboard", "admin")
            ->assertJsonPath("data.business.organization.name", $organization->name);
    }
}
