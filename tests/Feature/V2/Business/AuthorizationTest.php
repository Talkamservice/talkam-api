<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The security matrix for the business lane: role separation, tenant scoping
 * and the privacy boundary. Every endpoint that mutates or reads company data
 * is represented here.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Organization $organization, string $role): User
    {
        $user = User::factory()->create();

        OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
            "role" => $role,
        ]);

        return $user;
    }

    /** Every admin-only endpoint, as [method, uri, payload]. */
    private function adminEndpoints(Organization $organization): array
    {
        $invitation = Invitation::create([
            "uuid" => "IV_FORMATRIX" . $organization->id,
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $organization->id,
            "invitee_email" => "matrix@zenithbank.com",
            "invite_role" => "employee",
            "invite_expires_at" => now()->addDays(7),
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);

        return [
            ["get", "/api/v2/business/organization", []],
            ["post", "/api/v2/business/organization/seats", ["seats_licensed" => 10, "therapist_access" => true]],
            ["post", "/api/v2/business/organization/plan", ["pay_method" => "invoice"]],
            ["post", "/api/v2/business/organization/bench", ["bench_topics" => ["anxiety"]]],
            ["get", "/api/v2/business/invitations", []],
            ["post", "/api/v2/business/invitations", ["invites" => [["email" => "x@zenithbank.com", "role" => "employee"]]]],
            ["post", "/api/v2/business/invitations/{$invitation->id}/resend", []],
            ["post", "/api/v2/business/invitations/{$invitation->id}/revoke", []],
            ["post", "/api/v2/business/domain/verify", ["code" => "123456"]],
        ];
    }

    private function hit(string $method, string $uri, array $payload)
    {
        return $method === "get" ? $this->getJson($uri) : $this->postJson($uri, $payload);
    }

    /* ── Role separation ────────────────────────────────────────────────── */

    public function test_an_employee_is_refused_every_admin_endpoint(): void
    {
        $organization = Organization::factory()->create();
        $employee = $this->memberOf($organization, OrganizationConstants::ROLE_EMPLOYEE);

        Sanctum::actingAs($employee);

        foreach ($this->adminEndpoints($organization) as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)
                ->assertStatus(403, "{$method} {$uri} should be forbidden for an employee");
        }
    }

    public function test_a_therapist_is_refused_every_admin_endpoint(): void
    {
        $organization = Organization::factory()->create();
        $therapist = $this->memberOf($organization, OrganizationConstants::ROLE_THERAPIST);

        Sanctum::actingAs($therapist);

        foreach ($this->adminEndpoints($organization) as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)
                ->assertStatus(403, "{$method} {$uri} should be forbidden for a therapist");
        }
    }

    public function test_a_user_with_no_membership_is_refused_every_business_endpoint(): void
    {
        $organization = Organization::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        foreach ($this->adminEndpoints($organization) as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)->assertStatus(403);
        }

        $this->postJson("/api/v2/business/onboarding/topics", ["interests" => [1]])->assertStatus(403);
        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 1, "anxiety" => 1, "sleep" => 1, "relationships" => 1],
        ])->assertStatus(403);
    }

    public function test_an_inactive_membership_grants_nothing(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMember::factory()->admin()->inactive()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v2/business/organization")->assertStatus(403);
        $this->getJson("/api/v2/user/me")->assertJsonPath("data.business.is_member", false);
    }

    public function test_a_suspended_company_account_is_locked_out(): void
    {
        $organization = Organization::factory()->suspended()->create();
        $admin = $this->memberOf($organization, OrganizationConstants::ROLE_ADMIN);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v2/business/organization")->assertStatus(403);
    }

    public function test_unauthenticated_callers_get_401(): void
    {
        $organization = Organization::factory()->create();

        foreach ($this->adminEndpoints($organization) as [$method, $uri, $payload]) {
            $this->hit($method, $uri, $payload)->assertStatus(401);
        }
    }

    /* ── Tenant scoping ─────────────────────────────────────────────────── */

    public function test_an_admin_cannot_touch_another_tenants_invitation(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $admin_a = $this->memberOf($mine, OrganizationConstants::ROLE_ADMIN);
        $this->memberOf($theirs, OrganizationConstants::ROLE_ADMIN);

        $foreign = Invitation::create([
            "uuid" => "IV_FOREIGN",
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $theirs->id,
            "invitee_email" => "someone@othercorp.com",
            "invite_role" => "employee",
            "invite_expires_at" => now()->addDays(7),
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);

        Sanctum::actingAs($admin_a);

        $this->postJson("/api/v2/business/invitations/{$foreign->id}/resend")->assertStatus(404);
        $this->postJson("/api/v2/business/invitations/{$foreign->id}/revoke")->assertStatus(404);

        // Untouched.
        $foreign->refresh();
        $this->assertSame(StatusConstants::PENDING, $foreign->status);
        $this->assertNull($foreign->revoke_at);
    }

    public function test_the_invitation_policy_refuses_a_v1_or_group_invite(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->memberOf($organization, OrganizationConstants::ROLE_ADMIN);

        $v1_invite = Invitation::create([
            "uuid" => "IV_V1LANE",
            "invited_by" => User::factory()->create()->id,
            "invitee_email" => "v1@talkam.net",
            "source" => "admin",
            "status" => StatusConstants::PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/invitations/{$v1_invite->id}/revoke")->assertStatus(404);
        $this->assertSame(StatusConstants::PENDING, $v1_invite->refresh()->status);
    }

    public function test_an_admin_never_sees_another_tenants_roster(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $admin = $this->memberOf($mine, OrganizationConstants::ROLE_ADMIN);

        Invitation::create([
            "uuid" => "IV_MINE",
            "invited_by" => $admin->id,
            "organization_id" => $mine->id,
            "invitee_email" => "mine@zenithbank.com",
            "invite_role" => "employee",
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);
        Invitation::create([
            "uuid" => "IV_THEIRS",
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $theirs->id,
            "invitee_email" => "theirs@othercorp.com",
            "invite_role" => "employee",
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);

        Sanctum::actingAs($admin);

        $emails = collect($this->getJson("/api/v2/business/invitations")->json("data.data"))->pluck("email");

        $this->assertSame(["mine@zenithbank.com"], $emails->all());
    }

    /**
     * The organization is derived from the caller's membership, never from the
     * payload — sending someone else's ids changes nothing.
     */
    public function test_organization_ids_in_the_payload_are_ignored(): void
    {
        $mine = Organization::factory()->seats(100)->create();
        $theirs = Organization::factory()->seats(100)->create();

        $admin = $this->memberOf($mine, OrganizationConstants::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/business/organization/seats", [
            "organization_id" => $theirs->id,
            "id" => $theirs->id,
            "seats_licensed" => 42,
            "therapist_access" => false,
        ])->assertStatus(200)->assertJsonPath("data.organization.id", $mine->id);

        $this->assertSame(42, (int) $mine->refresh()->seats_licensed);
        $this->assertSame(100, (int) $theirs->refresh()->seats_licensed);
    }

    /* ── Privacy boundary ───────────────────────────────────────────────── */

    /**
     * The whole admin lane in this section must expose no individual employee
     * wellbeing data. Self-check answers are the only individual data §01
     * stores, and there is no admin-reachable route to them.
     */
    public function test_no_admin_endpoint_exposes_individual_self_check_answers(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->memberOf($organization, OrganizationConstants::ROLE_ADMIN);
        $employee = $this->memberOf($organization, OrganizationConstants::ROLE_EMPLOYEE);

        Sanctum::actingAs($employee);
        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 3, "anxiety" => 3, "sleep" => 3, "relationships" => 3],
        ])->assertStatus(200);

        Sanctum::actingAs($admin);

        // The employee-scoped read is refused outright for an admin.
        $this->getJson("/api/v2/business/self-check")->assertStatus(403);

        // And nothing in the admin surfaces carries a score or a category.
        foreach (["/api/v2/business/organization", "/api/v2/business/invitations"] as $uri) {
            $body = $this->getJson($uri)->assertStatus(200)->getContent();

            foreach (["\"score\"", "self_check", "answers", "primary_concern"] as $needle) {
                $this->assertStringNotContainsString($needle, $body, "{$uri} leaked {$needle}");
            }
        }
    }

    public function test_an_employee_cannot_read_another_employees_self_check(): void
    {
        $organization = Organization::factory()->create();
        $one = $this->memberOf($organization, OrganizationConstants::ROLE_EMPLOYEE);
        $two = $this->memberOf($organization, OrganizationConstants::ROLE_EMPLOYEE);

        Sanctum::actingAs($one);
        $this->postJson("/api/v2/business/self-check", [
            "answers" => ["work" => 3, "anxiety" => 0, "sleep" => 0, "relationships" => 0],
        ])->assertStatus(200);

        // The second employee's read is scoped to their own membership and is empty.
        Sanctum::actingAs($two);
        $this->getJson("/api/v2/business/self-check")
            ->assertStatus(200)
            ->assertJsonPath("data.completed", false)
            ->assertJsonPath("data.answers", []);
    }

    /* ── Public endpoints stay public but say little ────────────────────── */

    public function test_pricing_config_needs_no_session_and_carries_no_tenant_data(): void
    {
        Organization::factory()->create(["name" => "Secret Corp"]);

        $body = $this->getJson("/api/v2/business/pricing-config")->assertStatus(200)->getContent();

        $this->assertStringNotContainsString("Secret Corp", $body);
    }
}
