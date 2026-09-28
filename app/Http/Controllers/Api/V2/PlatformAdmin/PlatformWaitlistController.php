<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Waitlist;
use Exception;

class PlatformWaitlistController extends Controller
{
    public function index()
    {
        try {
            return ApiHelper::validResponse("Waitlist returned successfully", [
                "waitlist_mode" => (bool) PlatformSetting::get('waitlist_mode', '0'),
                "signups" => Waitlist::latest()->paginate(20),
                "total" => Waitlist::count(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
