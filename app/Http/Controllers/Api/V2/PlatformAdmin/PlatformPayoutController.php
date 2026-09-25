<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessAllPayoutsJob;
use App\Services\PlatformAdmin\PlatformPayoutService;
use Illuminate\Http\Request;
use Exception;

class PlatformPayoutController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Payouts returned successfully", [
                "overview" => PlatformPayoutService::overview(),
                "payouts" => PlatformPayoutService::list($request->input('tab', 'pending'), $page),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /** Reuses PayoutService::withdraw() — see PlatformPayoutService's
     *  class docblock. Runs synchronously since it's a deliberate,
     *  single-therapist action an admin is watching for a result. */
    public function process($therapistId)
    {
        try {
            $payout = PlatformPayoutService::process((int) $therapistId);
            return ApiHelper::validResponse("Payout initiated", $payout);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    /** Queued — a batch of outbound Flutterwave calls (one per pending
     *  therapist) has no business blocking the HTTP request. The actual
     *  work (PlatformPayoutService::processAll()) is unchanged; only where
     *  it runs changed. Poll lastRun() for the result. */
    public function processAll()
    {
        try {
            ProcessAllPayoutsJob::dispatch(auth()->id());
            return ApiHelper::validResponse("Payout run queued", ["status" => "queued"]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function lastRun()
    {
        try {
            return ApiHelper::validResponse("Last payout run returned successfully", PlatformPayoutService::lastRun());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
