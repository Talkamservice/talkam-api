<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\User\AvatarService;
use App\Services\User\InterestService;
use App\Services\User\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public $user_service;
    public $interest_service;
    public $avatar_service;

    public function __construct()
    {
        $this->user_service = new UserService;
        $this->interest_service = new InterestService;
        $this->avatar_service = new AvatarService;
    }

    public function me()
    {
        try {
            $user = auth()->user();
            return ApiHelper::validResponse("User data retrieved successfully", UserResource::make($user));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function uploadAvatar(Request $request)
    {
        try {
            $user = auth()->user();
            $this->avatar_service->setUser($user)->update($request->all());
            return ApiHelper::validResponse("Avatar updated successfully", UserResource::make($user));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function update(Request $request)
    {
        try {
            $user = $this->user_service->update($request->all(), auth()->id());
            return ApiHelper::validResponse("User data retrieved successfully", UserResource::make($user));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function saveInterest(Request $request)
    {
        try {
            $response = $this->interest_service->setUser(auth()->user())->addRemove($request->all());
            $data = ["present" => $response];
            return ApiHelper::validResponse("Interest updated successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function eraseAccount(Request $request)
    {
        try {
            $this->user_service->eraseData();
            return ApiHelper::validResponse("Account erased successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function deleteAccount(Request $request)
    {
        try {
            $data = $request->validate([
                "reason" => "required|string",
            ]);

            $this->user_service->deleteAccount($data);
            return ApiHelper::validResponse("Account deleted successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
