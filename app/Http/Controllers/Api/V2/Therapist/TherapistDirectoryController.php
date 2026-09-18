<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistDirectoryService;
use App\Services\Therapist\TherapistSlotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class TherapistDirectoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $therapists = TherapistDirectoryService::list($request->all(), auth()->user())
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($therapists);
            $data["data"] = TherapistDirectoryService::cardsFor($therapists->getCollection());

            return ApiHelper::validResponse("Therapists returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $therapist = TherapistDirectoryService::getById($id, auth()->user());
            return ApiHelper::validResponse(
                "Therapist profile returned successfully",
                TherapistDirectoryService::profile($therapist)
            );
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function slots(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                "date" => "nullable|date_format:Y-m-d",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $therapist = TherapistDirectoryService::getById($id, auth()->user());
            $date = $validator->validated()["date"] ?? null;

            // No date = the reschedule/propose pickers just want "what's
            // soonest", so scan forward instead of requiring the caller to
            // already know a bookable day. Limit is generous (not the
            // default 6) because those pickers paginate client-side, 6 per
            // page, rather than only ever offering the first 6.
            $slots = $date
                ? TherapistSlotService::slotsFor($therapist, $date)
                : TherapistSlotService::upcomingSlots($therapist, 14, 200);

            return ApiHelper::validResponse("Slots returned successfully", [
                "date" => $date,
                "slots" => $slots,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
