<?php

namespace App\Http\Middleware;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use Closure;
use Illuminate\Http\Request;

/**
 * Domain confirmation gate. The deck is explicit that verifying the company
 * domain is "a requirement before any employee can be invited", so seats,
 * plan and invite endpoints all sit behind this.
 *
 * Runs after org.role, which is what puts the organization on the request.
 */
class EnsureOrganizationVerified
{
    public function handle(Request $request, Closure $next)
    {
        $organization = $request->attributes->get("organization");

        if (empty($organization) || !$organization->isVerified()) {
            return ApiHelper::problemResponse(
                "Confirm your business domain before setting up your team.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        return $next($request);
    }
}
