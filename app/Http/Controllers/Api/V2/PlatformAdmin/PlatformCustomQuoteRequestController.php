<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformCustomQuoteRequestService;
use Illuminate\Http\Request;
use Exception;

class PlatformCustomQuoteRequestController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Custom quote requests returned successfully", [
                "requests" => PlatformCustomQuoteRequestService::list($page),
                "overview" => PlatformCustomQuoteRequestService::overview(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function markContacted($id)
    {
        try {
            return ApiHelper::validResponse("Marked as contacted", PlatformCustomQuoteRequestService::markContacted((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }
}
