<?php

namespace App\Http\Controllers\Api\V1\Feedback;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\GeneralException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Feedback\FeedbackResource;
use App\Http\Resources\Waitlist\WaitlistResource;
use App\Models\Waitlist;
use App\Services\Feedback\FeedbackService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    protected $feedback_service;

    public function __construct()
    {
        $this->feedback_service = new FeedbackService;
    }

    public function index(Request $request)
    {
        try {
            $waitlists = Waitlist::latest()->status()->get();
            $data = WaitlistResource::collection($waitlists);
            return ApiHelper::validResponse("Waitlists returned successfully", $data);
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

    public function save(Request $request)
    {
        try {
            $feedback = $this->feedback_service->create($request->all());
            $data = FeedbackResource::make($feedback);
            return ApiHelper::validResponse("Feedback submitted successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE,  $request, $e);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE,  $request, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }
}
