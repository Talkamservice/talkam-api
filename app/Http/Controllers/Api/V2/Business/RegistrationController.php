<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\PinException;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\OrganizationResource;
use App\Http\Resources\Users\UserResource;
use App\Services\Business\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Company signup and domain confirmation — screens 1 and 2 of the B2B
 * onboarding wizard.
 */
class RegistrationController extends Controller
{
    public OrganizationService $organization_service;

    public function __construct()
    {
        $this->organization_service = new OrganizationService;
    }

    public function register(Request $request)
    {
        try {
            $result = $this->organization_service->register($request->all());
            $user = $result["user"];

            return ApiHelper::validResponse("Company account created successfully", [
                "user" => UserResource::make($user)->resolve(),
                "organization" => OrganizationResource::make($result["organization"])->resolve(),
                "token" => $user->createToken("api")->plainTextToken,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function verifyDomain(Request $request)
    {
        try {
            $organization = $this->organization_service->verifyDomain(auth()->user(), $request->all());

            return ApiHelper::validResponse("Business domain confirmed successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (PinException | InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, null);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
