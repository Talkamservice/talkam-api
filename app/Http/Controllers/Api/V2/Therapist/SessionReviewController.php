<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\SessionReviewService;
use App\Services\Therapist\TherapistDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class SessionReviewController extends Controller
{
    public $review_service;
    function __construct()
    {
        $this->review_service = new SessionReviewService;
    }

    public function store(Request $request, $booking)
    {
        try {
            $review = $this->review_service->create(auth()->user(), $booking, $request->all());
            return ApiHelper::validResponse("Review submitted successfully", [
                "id" => $review->id,
                "rating" => $review->rating,
                "comment" => $review->comment,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function index(Request $request, $therapist)
    {
        try {
            $model = TherapistDirectoryService::getById($therapist, auth()->user());

            $reviews = SessionReviewService::listFor($model->id)
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($reviews);
            $data["data"] = $reviews->getCollection()
                ->map(fn ($review) => SessionReviewService::anonymise($review));
            $data["histogram"] = TherapistDirectoryService::histogram($model);

            return ApiHelper::validResponse("Reviews returned successfully", $data);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
