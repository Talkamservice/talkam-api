<?php

namespace App\Http\Middleware;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use Closure;
use Illuminate\Http\Request;

/**
 * Role gate for the TalkAM for Business lane.
 *
 * Resolves the caller's single ACTIVE organization membership and rejects
 * unless its role is one of those listed on the route. The resolved membership
 * and organization are attached to the request so no controller re-reads them
 * — and, critically, so no controller can be tempted to take an organization
 * id from the client.
 *
 *   ->middleware("org.role:admin")
 *   ->middleware("org.role:employee,therapist")
 *   ->middleware("org.role")            any active member
 */
class EnsureOrganizationRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (empty($user)) {
            return ApiHelper::problemResponse("Unauthenticated", ApiConstants::AUTH_ERR_CODE, null, null);
        }

        $membership = $user->organizationMember();

        if (empty($membership) || empty($membership->organization)) {
            return ApiHelper::problemResponse(
                "You are not a member of any organization on TalkAM for Business.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        if (!empty($roles) && !in_array($membership->role, $roles, true)) {
            return ApiHelper::problemResponse(
                "You do not have permission to perform this action.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        if ($membership->organization->status === OrganizationConstants::STATUS_SUSPENDED) {
            return ApiHelper::problemResponse(
                "This company account is suspended. Contact TalkAM support.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        $request->attributes->set("organization_member", $membership);
        $request->attributes->set("organization", $membership->organization);

        return $next($request);
    }
}
