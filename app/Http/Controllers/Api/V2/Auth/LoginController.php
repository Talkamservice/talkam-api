<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\AuthException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\Auth\LoginService;
use App\Services\Auth\PinService;
use App\Services\Business\OrganizationService;
use App\Services\User\PrivacySettingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * v2 login: identical to v1 unless the user enabled 2FA — then the response
 * is a challenge (no token) and a TYPE_LOGIN email OTP is issued.
 */
class LoginController extends Controller
{
    public function login(Request $request)
    {
        try {
            $user = LoginService::authenticate($request->all());

            if (PrivacySettingService::twoFactorEnabled($user)) {
                (new PinService)->create($user, [
                    "type" => PinConstants::TYPE_LOGIN,
                    "expires_at" => now()->addSeconds(config("system.configuration.pin_expiry")),
                    "length" => 6,
                    "code_type" => "int",
                ]);

                return ApiHelper::validResponse("A verification code has been sent to your email", [
                    "two_factor_required" => true,
                    "email" => $user->email,
                ]);
            }

            $data["user"] = UserResource::make($user)->toArray($request);
            $data["token"] = $user->createToken('api')->plainTextToken;
            // TalkAM for Business role context — the web sign-in screen reads
            // business.dashboard to pick which of the three dashboards to land
            // on, so it needs no second round-trip. Non-members get is_member
            // false; mobile clients ignore the key.
            $data["business"] = OrganizationService::context($user);
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
}
