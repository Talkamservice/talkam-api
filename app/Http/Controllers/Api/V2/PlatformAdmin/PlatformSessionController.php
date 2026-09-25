<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformSessionService;
use Illuminate\Http\Request;
use Exception;

class PlatformSessionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $sessions = PlatformSessionService::list($request->only(['status', 'coverage_group', 'organization_id']), $page);
            return ApiHelper::validResponse("Sessions returned successfully", [
                "sessions" => $sessions,
                "overview" => PlatformSessionService::overview(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
