<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\Business\OrganizationService;
use App\Services\User\OnboardingService;
use Illuminate\Http\Request;
use Exception;

class UserController extends Controller
{
    /**
     * v1 me payload plus the derived onboarding object (step is computed,
     * never stored — the app resumes at the first false) and, for TalkAM for
     * Business members, the org/role context the web router uses to decide
     * which of the three dashboards to land on. Non-members get
     * business.is_member = false; mobile clients ignore the key.
     */
    public function me(Request $request)
    {
        try {
            $user = auth()->user();
            $data = UserResource::make($user)->toArray($request);
            $data["onboarding"] = OnboardingService::state($user);
            $data["business"] = OrganizationService::context($user);
            return ApiHelper::validResponse("User data retrieved successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
