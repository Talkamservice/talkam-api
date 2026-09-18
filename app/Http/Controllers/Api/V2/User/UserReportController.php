<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\UserReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class UserReportController extends Controller
{
    public $user_report_service;
    function __construct()
    {
        $this->user_report_service = new UserReportService;
    }

    public function store(Request $request)
    {
        try {
            $report = $this->user_report_service->create(auth()->user(), $request->all());
            return ApiHelper::validResponse("Report submitted successfully", [
                "id" => $report->id,
                "status" => $report->status,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
