<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\AccountDeactivation;
use Exception;

/**
 * Thin wrapper over the Blade AccountStatusController flow — split here
 * into explicit approve()/reject() intents (the legacy single toggle
 * action flips based on current status, which is confusing over an API).
 */
class PlatformDeactivationController extends Controller
{
    public function index()
    {
        try {
            $requests = AccountDeactivation::with('user')->latest()->paginate(20);
            return ApiHelper::validResponse("Deactivation requests returned successfully", $requests);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function approve($id)
    {
        try {
            $deactivation = AccountDeactivation::with('user')->findOrFail($id);
            $deactivation->update(['status' => StatusConstants::DISABLED]);
            $deactivation->user?->update(['status' => StatusConstants::DISABLED]);
            return ApiHelper::validResponse("Deactivation approved", $deactivation->refresh());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function reject($id)
    {
        try {
            $deactivation = AccountDeactivation::with('user')->findOrFail($id);
            $deactivation->update(['status' => StatusConstants::UNRESOLVED]);
            $deactivation->user?->update(['status' => StatusConstants::ACTIVE]);
            return ApiHelper::validResponse("Deactivation request rejected", $deactivation->refresh());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
