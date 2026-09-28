<?php

namespace App\Http\Middleware;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use Closure;
use Illuminate\Http\Request;

/**
 * Role gate for the Platform Admin panel (/platform on the web).
 *
 * Mirrors EnsureOrganizationRole: checks the caller holds one of the given
 * Spatie roles (Super Admin / Admin / Content Manager / Support Staff — see
 * PlatformAdminConstants) and attaches the resolved role to the request so
 * controllers never re-derive it.
 *
 *   ->middleware("platform.role:Super Admin")
 *   ->middleware("platform.role:Super Admin,Admin")
 *   ->middleware("platform.role")   any platform role
 */
class EnsurePlatformRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (empty($user)) {
            return ApiHelper::problemResponse("Unauthenticated", ApiConstants::AUTH_ERR_CODE, null, null);
        }

        $allowed = !empty($roles) ? $roles : \App\Constants\System\PlatformAdminConstants::ALL_ROLES;

        if (!$user->hasAnyRole($allowed)) {
            return ApiHelper::problemResponse(
                "You do not have permission to access the platform admin panel.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        $request->attributes->set(
            "platform_role",
            $user->getRoleNames()->intersect(\App\Constants\System\PlatformAdminConstants::ALL_ROLES)->first()
        );

        return $next($request);
    }
}
