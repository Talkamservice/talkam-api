<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformAuthService;
use Exception;

class PlatformAuthController extends Controller
{
    /**
     * Confirms the caller holds a platform role and returns it — the
     * platform login screen calls this right after /auth/login to decide
     * whether to land on /platform (the "platform.role" middleware already
     * rejected non-platform-admin callers before this runs).
     */
    public function session()
    {
        try {
            $user = auth()->user();

            return ApiHelper::validResponse("Session confirmed", [
                "user" => [
                    "id" => $user->id,
                    "name" => trim("$user->first_name $user->last_name"),
                    "email" => $user->email,
                    "avatar" => $user->avatar,
                ],
                "platform_role" => PlatformAuthService::context($user),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
