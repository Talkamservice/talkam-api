<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\Feedback\FeedbackService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformFeedbackController extends Controller
{
    public function index()
    {
        try {
            $feedback = Feedback::with('attachments')->latest()->paginate(20);
            return ApiHelper::validResponse("Feedback returned successfully", $feedback);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function resolve($id)
    {
        try {
            $feedback = (new FeedbackService)->changeStatus([], $id);
            return ApiHelper::validResponse("Feedback resolved", $feedback);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function respond(Request $request, $id)
    {
        try {
            (new FeedbackService)->respond($request, $id);
            return ApiHelper::validResponse("Response sent", []);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
