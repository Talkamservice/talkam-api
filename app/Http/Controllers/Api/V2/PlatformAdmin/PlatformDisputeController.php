<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformDisputeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformDisputeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Disputes returned successfully", [
                "disputes" => PlatformDisputeService::list($request->input('tab', 'open'), $page),
                "overview" => PlatformDisputeService::overview(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function startReview($id)
    {
        try {
            return ApiHelper::validResponse("Dispute moved to review", PlatformDisputeService::startReview((int) $id, auth()->id()));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function escalate($id)
    {
        try {
            return ApiHelper::validResponse("Dispute escalated", PlatformDisputeService::escalate((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function resolve(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                "resolution" => "required|string|max:2000",
                "status" => "required|string|in:Resolved,Dismissed",
                "notify" => "nullable|boolean",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $dispute = PlatformDisputeService::resolve((int) $id, $validator->validated(), auth()->id());
            return ApiHelper::validResponse("Dispute resolved", $dispute);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }
}
