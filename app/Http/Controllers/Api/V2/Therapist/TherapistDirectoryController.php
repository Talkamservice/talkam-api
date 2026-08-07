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
            $therapists = TherapistDirectoryService::list($request->all())
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($therapists);
            $data["data"] = $therapists->getCollection()
                ->map(fn ($therapist) => TherapistDirectoryService::card($therapist));

            return ApiHelper::validResponse("Therapists returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $therapist = TherapistDirectoryService::getById($id);
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
                "date" => "required|date_format:Y-m-d",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $therapist = TherapistDirectoryService::getById($id);
            $slots = TherapistSlotService::slotsFor($therapist, $validator->validated()["date"]);

            return ApiHelper::validResponse("Slots returned successfully", [
                "date" => $validator->validated()["date"],
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
