<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\EarningsLedgerService;
use Illuminate\Http\Request;
use Exception;

class EarningsController extends Controller
{
    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    /**
     * A business-employed therapist (web §04) is paid by their organisation, not
     * by TalkAM, so the whole Earnings module is hidden for them. Returning ₦0
     * would be wrong — they ARE paid, just not here — so this is a 403 with an
     * explanation the frontend surfaces as the "paid by business" tag.
     */
    private function forbiddenIfBusinessEmployed()
    {
        $employment = \App\Services\Therapist\TherapistDashboardService::employment(auth()->user());

        if ($employment["is_business_employed"]) {
            return ApiHelper::problemResponse(
                "Your sessions are paid directly by {$employment['employer_name']}, so TalkAM earnings do not apply to your account.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        return null;
    }

    public function dashboard()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        if ($blocked = $this->forbiddenIfBusinessEmployed()) {
            return $blocked;
        }

        try {
            $data = EarningsLedgerService::dashboard(auth()->user()->therapist);
            return ApiHelper::validResponse("Earnings dashboard returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function transactions(Request $request)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        if ($blocked = $this->forbiddenIfBusinessEmployed()) {
            return $blocked;
        }

        try {
            $rows = EarningsLedgerService::transactions(auth()->user()->therapist)
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($rows);
            $data["data"] = $rows->getCollection()->map(fn ($row) => [
                "id" => $row->id,
                "type" => $row->type,
                "amount" => $row->amount,
                "status" => $row->status,
                "reference" => $row->reference,
                "created_at" => formatDate($row->created_at),
            ]);

            return ApiHelper::validResponse("Transactions returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
