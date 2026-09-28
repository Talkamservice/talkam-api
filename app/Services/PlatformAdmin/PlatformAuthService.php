<?php

namespace App\Services\PlatformAdmin;

use App\Constants\System\PlatformAdminConstants;
use App\Models\User;

class PlatformAuthService
{
    /**
     * Login-response context (mirrors OrganizationService::context()'s
     * "business" key) — lets the platform login screen land on /platform
     * without a second round-trip.
     */
    public static function context(User $user): array
    {
        $role = $user->getRoleNames()->intersect(PlatformAdminConstants::ALL_ROLES)->first();

        return [
            'is_platform_admin' => !empty($role),
            'role' => $role,
        ];
    }
}
