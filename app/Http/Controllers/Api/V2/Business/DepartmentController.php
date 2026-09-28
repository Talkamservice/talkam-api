<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Business\DepartmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");

            return ApiHelper::validResponse(
                "Departments returned successfully",
                DepartmentService::list($organization)
            );
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function store(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $department = DepartmentService::create($organization, (string) $request->input("name", ""));

            return ApiHelper::validResponse("Department created successfully", $department);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function update(Request $request, $department)
    {
        try {
            $organization = $request->attributes->get("organization");
            $row = DepartmentService::update($organization, $department, (string) $request->input("name", ""));

            return ApiHelper::validResponse("Department updated successfully", $row);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function destroy(Request $request, $department)
    {
        try {
            $organization = $request->attributes->get("organization");
            DepartmentService::delete($organization, $department);

            return ApiHelper::validResponse("Department deleted successfully");
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    private function failure(Exception $e)
    {
        if ($e instanceof ValidationException) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        }

        if ($e instanceof ModelNotFoundException) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, null);
        }

        if ($e instanceof InvalidRequestException) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, null);
        }

        return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
    }
}
