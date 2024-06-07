<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\AuthException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\PreviewResource;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\OAuthLoginService;
use App\Services\Streak\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class LoginController extends Controller
{
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
            $user = User::where('email', $email)->first();

            $data = [
                "email" => $email,
                "new_user" => true,
            ];

            return ApiHelper::validResponse("Logged in successfully", $data);
        } catch (ValidationException $e) {
            $message = "The given data was invalid.";
            return ApiHelper::inputErrorResponse($message, ApiConstants::VALIDATION_ERR_CODE, $request, $e);
        } catch (AuthException $e) {
            $message = $e->getMessage();
            return ApiHelper::problemResponse($message, ApiConstants::BAD_REQ_ERR_CODE, $request, $e);
        } catch (Exception $e) {
            $message = 'Something went wrong while processing your request.';
            return ApiHelper::problemResponse($message, ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }
}
