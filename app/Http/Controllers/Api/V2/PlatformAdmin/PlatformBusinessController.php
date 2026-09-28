<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformBusinessService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformBusinessController extends Controller
{
    public function index(Request $request)
    {
        try {
            return ApiHelper::validResponse("Organizations returned successfully", [
                "organizations" => PlatformBusinessService::list($request->only(['search', 'tab'])),
                "overview" => PlatformBusinessService::overview(),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            return ApiHelper::validResponse("Organization returned successfully", PlatformBusinessService::show((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $organization = PlatformBusinessService::create($request->all());
            return ApiHelper::validResponse("Organization created", $organization);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            return ApiHelper::validResponse("Organization updated", PlatformBusinessService::update((int) $id, $request->only(['name', 'industry'])));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function destroy($id)
    {
        try {
            PlatformBusinessService::destroy((int) $id);
            return ApiHelper::validResponse("Organization removed", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function suspend($id)
    {
        try {
            return ApiHelper::validResponse("Organization suspended", PlatformBusinessService::suspend((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function reactivate($id)
    {
        try {
            return ApiHelper::validResponse("Organization reactivated", PlatformBusinessService::reactivate((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function employees(Request $request, $id)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Employees returned successfully", PlatformBusinessService::employees(
                (int) $id,
                $request->only(['department', 'status', 'search']),
                $page
            ));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function employeeDetail($id, $memberId)
    {
        try {
            return ApiHelper::validResponse("Employee returned successfully", PlatformBusinessService::employeeDetail((int) $id, $memberId));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function deactivateEmployee($id, $memberId)
    {
        try {
            PlatformBusinessService::deactivateEmployee((int) $id, $memberId);
            return ApiHelper::validResponse("Employee deactivated", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function reactivateEmployee($id, $memberId)
    {
        try {
            PlatformBusinessService::reactivateEmployee((int) $id, $memberId);
            return ApiHelper::validResponse("Employee reactivated", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function inviteEmployee(Request $request, $id)
    {
        try {
            $result = PlatformBusinessService::inviteEmployee((int) $id, $request->all());
            return ApiHelper::validResponse("Invitation sent", $result);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function therapists(Request $request, $id)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Therapist access returned successfully", PlatformBusinessService::therapistAccess((int) $id, $page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function invoices($id)
    {
        try {
            return ApiHelper::validResponse("Invoices returned successfully", PlatformBusinessService::invoices((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function activity($id)
    {
        try {
            return ApiHelper::validResponse("Activity log returned successfully", PlatformBusinessService::activity((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
