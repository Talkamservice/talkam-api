<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformDashboardService;
use Exception;

class PlatformDashboardController extends Controller
{
    public function index()
    {
        try {
            return ApiHelper::validResponse("Overview returned successfully", PlatformDashboardService::overview());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function navCounts()
    {
        try {
            return ApiHelper::validResponse("Nav counts returned successfully", PlatformDashboardService::navCounts());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
