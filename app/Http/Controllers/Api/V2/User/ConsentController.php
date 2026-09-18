<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class ConsentController extends Controller
{
    public $consent_service;
    function __construct()
    {
        $this->consent_service = new ConsentService;
    }

    public function index()
    {
        try {
            $data = ConsentService::state(auth()->user());
            return ApiHelper::validResponse("Consents returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $this->consent_service->update(auth()->user(), $request->all());
            return ApiHelper::validResponse("Consents updated successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
