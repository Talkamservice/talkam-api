<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\AuthException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\PreviewResource;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\OAuthLoginService;
use App\Services\Auth\VerifyService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public $user_service;
    public $verify_service;

    public function __construct()
    {
        $this->user_service = new UserService;
        $this->verify_service = new VerifyService;
    }
    public function loginPreview(Request $request)
    {
        try {
            $user = LoginService::preview($request->all());
            return ApiHelper::validResponse("User data retrieved successfully", PreviewResource::make($user));
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (AuthException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
    public function login(Request $request)
    {
        try {
            $user = LoginService::authenticate($request->all());
            $data["user"] =  UserResource::make($user)->toArray($request);
            $data["token"] = $user->createToken('api')->plainTextToken;
            LoginService::newLogin($user);
            return ApiHelper::validResponse("Logged in successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (AuthException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function oauthLogin(Request $request)
    {
        try {
            $data = $request->validate([
                'fcm_token' => 'nullable|string',
                "token" => "required|string",
                'provider' => 'required|in:google,apple,facebook,tiktok',
            ]);

            $oauth_login_service = new OAuthLoginService;
            $oauth_login_service->setToken($data["token"])
                ->setProvider($data["provider"]);

            $payload =  $oauth_login_service->byProvider();

            if (empty($payload)) {
                throw new AuthException("Unable to login via oauth");
            }

            $email = $payload["email"];
            $full_name = explode(" ", $payload["name"]);
            $user = User::where('email', $email)->first();

            $data["new_user"] = false;
            if (empty($user)) {
                $user = $this->user_service->create([
                    'first_name' => $full_name[0],
                    'last_name' => $full_name[1] ?? $full_name[0],
                    "email" => $email,
                    "role" => UserConstants::USER,
                    'password' => Hash::make(Str::random(64)),
                    'registration_platform' => $request->provider,
                    'fcm_token' => $request->fcm_token,
                    "social_id" => isset($payload['social_id']) ? $payload['social_id'] :  null
                ]);
                $data["new_user"] = true;
            }

            if (empty($user->email_verified_at)) {
                $user->update([
                    "email_verified_at" => now()
                ]);
            }
            
            $data["user"] =  UserResource::make($user)->toArray($request);
            $data["token"] = $user->createToken('api')->plainTextToken;
            LoginService::newLogin($user);
            return ApiHelper::validResponse("Logged in successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse("The given data was invalid.", ApiConstants::VALIDATION_ERR_CODE, $request, $e);
        } catch (AuthException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $request, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse('Something went wrong while processing your request.', ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }
}
