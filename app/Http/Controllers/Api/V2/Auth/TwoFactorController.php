<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Constants\Auth\PinConstants;
use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\PinException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Services\Auth\LoginService;
use App\Services\Auth\PinService;
use App\Services\Business\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class TwoFactorController extends Controller
{
    /**
     * Second step of a 2FA login: the TYPE_LOGIN OTP mints the token.
     */
    public function verify(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email",
                "code" => "required|string",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();

            $check = PinService::verify([
                "code" => $validated["code"],
                "type" => PinConstants::TYPE_LOGIN,
            ]);

            $user = $check["user"];

            if (empty($user) || strtolower($user->email) != strtolower($validated["email"])) {
                throw new PinException("The code is invalid. Kindly request a new code.");
            }

            $data["user"] = UserResource::make($user)->toArray($request);
            $data["token"] = $user->createToken('api')->plainTextToken;
            // Same business context the non-2FA login returns — the 2FA screen
            // is the last step before landing on a dashboard.
            $data["business"] = OrganizationService::context($user);
            LoginService::newLogin($user);

            return ApiHelper::validResponse("Logged in successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (PinException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
