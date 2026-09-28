<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformActivityLogService;
use Illuminate\Http\Request;
use Exception;

class PlatformActivityLogController extends Controller
{
    public function index(Request $request)
    {
        try {
            return ApiHelper::validResponse("Activity logs returned successfully", PlatformActivityLogService::list($request));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
