<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear("");
    }

    private function admin(int $seats = 250): array
    {
        $organization = Organization::factory()->seats($seats)->create();
        $user = User::factory()->create();

        OrganizationMember::factory()->admin()->create([
            "organization_id" => $organization->id,
            "user_id" => $user->id,
        ]);

        Sanctum::actingAs($user);

        return [$organization, $user];
    }

    private function fourInvites(): array
    {
        return [
            ["email" => "chidinma.eze@zenithbank.com", "role" => "employee", "department" => "Technology"],
            ["email" => "tunde.balogun@zenithbank.com", "role" => "employee", "department" => "Finance"],
            ["email" => "dr.ngozi.uba@practice.ng", "role" => "therapist", "department" => "Clinical"],
            ["email" => "fatima.bello@zenithbank.com", "role" => "employee", "department" => "Operations"],
        ];
    }

    /* ── Sending ────────────────────────────────────────────────────────── */

    public function test_admin_sends_four_invites_with_roles_departments_and_expiry(): void
    {
        [$organization, $admin] = $this->admin();

        $this->postJson("/api/v2/business/invitations", ["invites" => $this->fourInvites()])
            ->assertStatus(200)
            ->assertJsonPath("data.sent", 4);

        $invitations = Invitation::where("organization_id", $organization->id)->get();
        $this->assertCount(4, $invitations);

        $therapist = $invitations->firstWhere("invitee_email", "dr.ngozi.uba@practice.ng");
        $this->assertSame(OrganizationConstants::ROLE_THERAPIST, $therapist->invite_role);
        $this->assertSame("Clinical", $therapist->department);
        $this->assertSame(StatusConstants::PENDING, $therapist->status);
        $this->assertSame($admin->id, $therapist->invited_by);
        $this->assertSame(OrganizationConstants::INVITE_SOURCE, $therapist->source);
        $this->assertNotNull($therapist->invite_expires_at);
        $this->assertSame(
            now()->addDays(7)->toDateString(),
            \Carbon\Carbon::parse($therapist->invite_expires_at)->toDateString()
        );

        // Every uuid is unique and single-use.
        $this->assertSame(4, $invitations->pluck("uuid")->unique()->count());
    }

    public function test_admin_cannot_invite_another_admin(): void
    {
        $this->admin();

        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "someone@zenithbank.com", "role" => "admin"]],
        ])->assertStatus(422);

        $this->assertSame(0, Invitation::count());
    }

    public function test_duplicate_pending_invite_rejected(): void
    {
        [$organization] = $this->admin();

        Invitation::create([
            "uuid" => "IV_EXISTING",
            "invited_by" => $organization->created_by ?? User::factory()->create()->id,
            "organization_id" => $organization->id,
            "invitee_email" => "chidinma.eze@zenithbank.com",
            "invite_role" => "employee",
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);

        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "chidinma.eze@zenithbank.com", "role" => "employee"]],
        ])->assertStatus(400);

        $this->assertSame(1, Invitation::where("organization_id", $organization->id)->count());
    }

    public function test_duplicate_within_the_same_batch_rejected(): void
    {
        [$organization] = $this->admin();

        $this->postJson("/api/v2/business/invitations", [
            "invites" => [
                ["email" => "same@zenithbank.com", "role" => "employee"],
                ["email" => "SAME@zenithbank.com", "role" => "therapist"],
            ],
        ])->assertStatus(400);

        $this->assertSame(0, Invitation::where("organization_id", $organization->id)->count());
    }

    public function test_inviting_an_existing_member_rejected(): void
    {
        [$organization] = $this->admin();

        $member = User::factory()->create(["email" => "already@zenithbank.com"]);
        OrganizationMember::factory()->create([
            "organization_id" => $organization->id,
            "user_id" => $member->id,
        ]);

        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "already@zenithbank.com", "role" => "employee"]],
        ])->assertStatus(400);
    }

    public function test_invites_beyond_licensed_seats_rejected(): void
    {
        // 3 seats, 1 already held by the admin → 2 remaining.
        [$organization] = $this->admin(3);

        $this->postJson("/api/v2/business/invitations", ["invites" => $this->fourInvites()])
            ->assertStatus(400);

        $this->assertSame(0, Invitation::where("organization_id", $organization->id)->count());

        // Two fits exactly.
        $this->postJson("/api/v2/business/invitations", [
            "invites" => array_slice($this->fourInvites(), 0, 2),
        ])->assertStatus(200);

        // A third does not.
        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "one.more@zenithbank.com", "role" => "employee"]],
        ])->assertStatus(400);
    }

    /* ── Roster ─────────────────────────────────────────────────────────── */

    public function test_roster_lists_only_this_organizations_invites_and_never_leaks_the_uuid(): void
    {
        [$organization] = $this->admin();

        $this->postJson("/api/v2/business/invitations", ["invites" => $this->fourInvites()])->assertStatus(200);

        // Another tenant's invite, plus a v1/group invite with no organization.
        $other = Organization::factory()->create();
        Invitation::create([
            "uuid" => "IV_OTHERORG",
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $other->id,
            "invitee_email" => "someone@othercorp.com",
            "invite_role" => "employee",
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);
        Invitation::create([
            "uuid" => "IV_V1ADMIN",
            "invited_by" => User::factory()->create()->id,
            "invitee_email" => "v1admin@talkam.net",
            "source" => "admin",
            "status" => StatusConstants::PENDING,
        ]);

        $response = $this->getJson("/api/v2/business/invitations")->assertStatus(200);

        $emails = collect($response->json("data.data"))->pluck("email");
        $this->assertCount(4, $emails);
        $this->assertFalse($emails->contains("someone@othercorp.com"));
        $this->assertFalse($emails->contains("v1admin@talkam.net"));

        $response->assertJsonMissing(["uuid" => "IV_OTHERORG"]);
        foreach ($response->json("data.data") as $row) {
            $this->assertArrayNotHasKey("uuid", $row);
        }
    }

    public function test_resend_extends_expiry_and_revoke_cancels(): void
    {
        [$organization] = $this->admin();
        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "chidinma.eze@zenithbank.com", "role" => "employee"]],
        ])->assertStatus(200);

        $invitation = Invitation::where("organization_id", $organization->id)->first();
        $invitation->update(["invite_expires_at" => now()->addDay()]);

        $this->postJson("/api/v2/business/invitations/{$invitation->id}/resend")->assertStatus(200);
        $this->assertSame(
            now()->addDays(7)->toDateString(),
            \Carbon\Carbon::parse($invitation->refresh()->invite_expires_at)->toDateString()
        );

        $this->postJson("/api/v2/business/invitations/{$invitation->id}/revoke")->assertStatus(200);
        $invitation->refresh();
        $this->assertSame(StatusConstants::CANCELLED, $invitation->status);
        $this->assertNotNull($invitation->revoke_at);

        // A revoked invite can be neither resent nor revoked again.
        $this->postJson("/api/v2/business/invitations/{$invitation->id}/resend")->assertStatus(400);
        $this->postJson("/api/v2/business/invitations/{$invitation->id}/revoke")->assertStatus(400);
    }

    /* ── Landing (public) ───────────────────────────────────────────────── */

    public function test_landing_returns_the_minimal_payload_and_stamps_opened_at(): void
    {
        [$organization] = $this->admin();
        $this->postJson("/api/v2/business/invitations", [
            "invites" => [["email" => "chidinma.eze@zenithbank.com", "role" => "employee", "department" => "Technology"]],
        ])->assertStatus(200);

        $invitation = Invitation::where("organization_id", $organization->id)->first();
        $this->assertNull($invitation->opened_at);

        $response = $this->getJson("/api/v2/business/invitations/token/{$invitation->uuid}")
            ->assertStatus(200)
            ->assertJsonPath("data.organization_name", $organization->name)
            ->assertJsonPath("data.email", "chidinma.eze@zenithbank.com")
            ->assertJsonPath("data.role", "employee");

        // Nothing beyond the company name is exposed on this unauthenticated route.
        $data = $response->json("data");
        foreach (["seats_licensed", "seats_used", "members", "domain", "invited_by", "id"] as $leak) {
            $this->assertArrayNotHasKey($leak, $data);
        }

        $this->assertNotNull($invitation->refresh()->opened_at);
    }

    public function test_landing_rejects_unknown_revoked_and_expired_tokens(): void
    {
        [$organization] = $this->admin();

        $this->getJson("/api/v2/business/invitations/token/IV_NOPE")->assertStatus(404);

        $revoked = Invitation::create([
            "uuid" => "IV_REVOKED",
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $organization->id,
            "invitee_email" => "revoked@zenithbank.com",
            "invite_role" => "employee",
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::CANCELLED,
        ]);
        $this->getJson("/api/v2/business/invitations/token/{$revoked->uuid}")->assertStatus(400);

        $expired = Invitation::create([
            "uuid" => "IV_EXPIRED",
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $organization->id,
            "invitee_email" => "expired@zenithbank.com",
            "invite_role" => "employee",
            "invite_expires_at" => now()->subDay(),
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);
        $this->getJson("/api/v2/business/invitations/token/{$expired->uuid}")->assertStatus(400);
    }

    /** A group invite's uuid must not resolve through the business lane. */
    public function test_landing_ignores_non_organization_invites(): void
    {
        $invitation = Invitation::create([
            "uuid" => "IV_GROUPONE",
            "invited_by" => User::factory()->create()->id,
            "invitee_email" => "member@example.com",
            "source" => "group",
            "status" => StatusConstants::PENDING,
        ]);

        $this->getJson("/api/v2/business/invitations/token/{$invitation->uuid}")->assertStatus(404);
    }

    /* ── Accept (public) ────────────────────────────────────────────────── */

    private function pendingInvite(Organization $organization, string $role = "employee"): Invitation
    {
        return Invitation::create([
            "uuid" => "IV_ACCEPTME",
            "invited_by" => User::factory()->create()->id,
            "organization_id" => $organization->id,
            "invitee_email" => "chidinma.eze@zenithbank.com",
            "invite_role" => $role,
            "department" => "Technology",
            "invite_expires_at" => now()->addDays(7),
            "source" => OrganizationConstants::INVITE_SOURCE,
            "status" => StatusConstants::PENDING,
        ]);
    }

    public function test_accept_creates_the_account_and_an_active_membership(): void
    {
        $organization = Organization::factory()->create();
        $invitation = $this->pendingInvite($organization);

        $response = $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Chidinma Eze",
            "password" => "Passw0rd12!",
        ])
            ->assertStatus(200)
            ->assertJsonPath("data.role", "employee");

        $this->assertNotEmpty($response->json("data.token"));

        $user = User::where("email", "chidinma.eze@zenithbank.com")->first();
        $this->assertNotNull($user);
        $this->assertSame("Chidinma", $user->first_name);
        $this->assertSame("Eze", $user->last_name);
        $this->assertNotNull($user->email_verified_at);

        $membership = OrganizationMember::where("user_id", $user->id)->first();
        $this->assertSame($organization->id, $membership->organization_id);
        $this->assertSame(OrganizationConstants::ROLE_EMPLOYEE, $membership->role);
        $this->assertSame(OrganizationConstants::MEMBER_ACTIVE, $membership->status);
        $this->assertSame("Technology", $membership->department);

        $invitation->refresh();
        $this->assertSame(StatusConstants::ACCEPTED, $invitation->status);
        $this->assertSame($user->id, $invitation->user_id);
        $this->assertNotNull($invitation->response_date);
    }

    public function test_accept_uses_the_invitation_email_not_anything_the_caller_sends(): void
    {
        $organization = Organization::factory()->create();
        $invitation = $this->pendingInvite($organization);

        $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Someone Else",
            "password" => "Passw0rd12!",
            "email" => "attacker@evil.com",
            "role" => "admin",
        ])->assertStatus(200);

        $this->assertNull(User::where("email", "attacker@evil.com")->first());

        $user = User::where("email", "chidinma.eze@zenithbank.com")->first();
        $this->assertNotNull($user);
        $this->assertSame(
            OrganizationConstants::ROLE_EMPLOYEE,
            OrganizationMember::where("user_id", $user->id)->first()->role
        );
    }

    public function test_accept_twice_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $invitation = $this->pendingInvite($organization);

        $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Chidinma Eze",
            "password" => "Passw0rd12!",
        ])->assertStatus(200);

        $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Chidinma Eze",
            "password" => "Passw0rd12!",
        ])->assertStatus(400);

        $this->assertSame(1, OrganizationMember::count());
    }

    public function test_accept_rejects_a_weak_password(): void
    {
        $organization = Organization::factory()->create();
        $invitation = $this->pendingInvite($organization);

        $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Chidinma Eze",
            "password" => "password",
        ])->assertStatus(422);

        $this->assertSame(0, User::where("email", "chidinma.eze@zenithbank.com")->count());
        $this->assertSame(StatusConstants::PENDING, $invitation->refresh()->status);
    }

    public function test_a_therapist_invite_creates_a_therapist_role_membership(): void
    {
        $organization = Organization::factory()->create();
        $invitation = $this->pendingInvite($organization, "therapist");

        $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Ngozi Uba",
            "password" => "Passw0rd12!",
        ])->assertStatus(200)->assertJsonPath("data.role", "therapist");

        $user = User::where("email", "chidinma.eze@zenithbank.com")->first();
        $this->assertSame(
            OrganizationConstants::ROLE_THERAPIST,
            OrganizationMember::where("user_id", $user->id)->first()->role
        );
    }

    public function test_a_user_already_in_another_organization_cannot_accept(): void
    {
        $organization = Organization::factory()->create();
        $invitation = $this->pendingInvite($organization);

        $existing = User::factory()->create(["email" => "chidinma.eze@zenithbank.com"]);
        OrganizationMember::factory()->create(["user_id" => $existing->id]);

        $this->postJson("/api/v2/business/invitations/token/{$invitation->uuid}/accept", [
            "full_name" => "Chidinma Eze",
            "password" => "Passw0rd12!",
        ])->assertStatus(400);

        $this->assertSame(1, OrganizationMember::where("user_id", $existing->id)->count());
    }
}
