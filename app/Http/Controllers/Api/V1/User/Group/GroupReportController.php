<?php

namespace App\Http\Controllers\Api\V1\User\Group;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Report\Group\GroupReportService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupReportController extends Controller
{
    protected $group_report_service;


    public function __construct()
    {
        $this->group_report_service = new GroupReportService;
    }

    public function report(Request $request)
    {
        try {
            $response = $this->group_report_service->reportGroup($request->all());
            return ApiHelper::validResponse("Report submitted successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
