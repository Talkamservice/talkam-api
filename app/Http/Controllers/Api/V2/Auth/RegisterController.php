<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\Auth\V2\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class RegisterController extends Controller
{
    public $register_service;
    function __construct()
    {
        $this->register_service = new RegistrationService;
    }

    public function register(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = $this->register_service->create($request->all());
            $data["token"] = $user->createToken('auth')->plainTextToken;
            $data["user"] = UserResource::make($user);
            $this->register_service->postRegisterActions($user);
            DB::commit();
            return ApiHelper::validResponse("User registered successfully", $data);
        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiHelper::problemResponse(
                $this->serverErrorMessage,
                ApiConstants::SERVER_ERR_CODE,
                null,
                $e
            );
        }
    }
}
