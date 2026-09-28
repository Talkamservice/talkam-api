<?php

namespace App\Http\Middleware;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use Closure;
use Illuminate\Http\Request;

/**
 * Danger Zone enforcement (web §03 Settings) for the generic /user/* lane —
 * bookings, messaging, mood check-ins, everything a member actually uses the
 * app for. That lane is deliberately NOT org-gated (direct consumers hit the
 * same routes), so a user with no active organization membership is always
 * unaffected here.
 *
 * For org members: a cancelled/suspended ORGANIZATION blocks every role tied
 * to it (the whole company relationship with TalkAM has ended or paused).
 * The narrower, admin-reversible employees_suspended_at flag blocks the
 * employee role only — an admin who suspended their own employees keeps
 * using the app normally.
 */
class EnsureOrgMembershipActive
{
    public function handle(Request $request, Closure $next)
    {
        $membership = $request->user()?->organizationMember();

        if (empty($membership) || empty($membership->organization)) {
            return $next($request);
        }

        $organization = $membership->organization;

        if (in_array($organization->status, [
            OrganizationConstants::STATUS_SUSPENDED,
            OrganizationConstants::STATUS_CANCELLED,
        ], true)) {
            return ApiHelper::problemResponse(
                "Your company's TalkAM account is no longer active. Contact your HR admin.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        if (
            $membership->role === OrganizationConstants::ROLE_EMPLOYEE
            && !empty($organization->employees_suspended_at)
        ) {
            return ApiHelper::problemResponse(
                "Your company has temporarily suspended employee access. Contact your HR admin.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        return $next($request);
    }
}
