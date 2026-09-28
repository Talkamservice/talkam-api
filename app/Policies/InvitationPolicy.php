<?php

namespace App\Policies;

use App\Constants\Business\OrganizationConstants;
use App\Models\Invitation;
use App\Models\User;

/**
 * Org-invite object authorization. Every check compares the invitation's own
 * organization_id against the caller's active admin membership, so an id
 * belonging to another tenant is rejected regardless of how it was obtained.
 *
 * v1 admin invites and §05 group invites (organization_id null) are never
 * manageable through this lane.
 */
class InvitationPolicy
{
    public function view(User $user, Invitation $invitation): bool
    {
        return $this->adminOfInvitationOrg($user, $invitation);
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->adminOfInvitationOrg($user, $invitation);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $this->adminOfInvitationOrg($user, $invitation);
    }

    private function adminOfInvitationOrg(User $user, Invitation $invitation): bool
    {
        if (empty($invitation->organization_id)) {
            return false;
        }

        return $user->organizationMemberships()
            ->where("organization_id", $invitation->organization_id)
            ->where("role", OrganizationConstants::ROLE_ADMIN)
            ->where("status", OrganizationConstants::MEMBER_ACTIVE)
            ->exists();
    }
}
