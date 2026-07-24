<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\CareTeamService;
use Exception;

class CareTeamController extends Controller
{
    /**
     * The member's own care-team card. Scoped to auth()->user() — there is no
     * id parameter, so there is no way to read anyone else's.
     */
    public function show()
    {
        try {
            return ApiHelper::validResponse(
                "Care team returned successfully",
                CareTeamService::forUser(auth()->user())
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
