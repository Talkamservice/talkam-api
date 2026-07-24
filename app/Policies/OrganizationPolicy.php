<?php

namespace App\Policies;

use App\Constants\Business\OrganizationConstants;
use App\Models\Organization;
use App\Models\User;

/**
 * Object-level authorization for company accounts.
 *
 * The org.role middleware answers "what may this role do?"; this answers
 * "may this user touch THIS organization?". Both must pass — the middleware
 * alone would be enough only for as long as no endpoint ever accepts an
 * organization id, and that is not an assumption worth depending on.
 */
class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->activeMemberOf($user, $organization) !== null;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->activeMemberOf($user, $organization)?->role === OrganizationConstants::ROLE_ADMIN;
    }

    /** Aggregate insight surfaces — admins of that org only. */
    public function viewInsights(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }

    public function invite(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization) && $organization->isVerified();
    }

    private function activeMemberOf(User $user, Organization $organization)
    {
        return $user->organizationMemberships()
            ->where("organization_id", $organization->id)
            ->where("status", OrganizationConstants::MEMBER_ACTIVE)
            ->first();
    }
}
