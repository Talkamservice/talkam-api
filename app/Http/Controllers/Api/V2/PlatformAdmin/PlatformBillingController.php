<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformBillingService;
use Exception;

class PlatformBillingController extends Controller
{
    public function index()
    {
        try {
            return ApiHelper::validResponse("Billing overview returned successfully", [
                "overview" => PlatformBillingService::overview(),
                "invoices" => PlatformBillingService::invoices(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
