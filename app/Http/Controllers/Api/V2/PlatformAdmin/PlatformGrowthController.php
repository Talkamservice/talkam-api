<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformGrowthService;
use Illuminate\Http\Request;
use Exception;

class PlatformGrowthController extends Controller
{
    public function index(Request $request)
    {
        try {
            $overview = PlatformGrowthService::overview($request->input('range', '12w'));
            return ApiHelper::validResponse("Growth overview returned successfully", $overview);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
