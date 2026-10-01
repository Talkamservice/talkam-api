<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class FcmTokenController extends Controller
{
    /**
     * Mobile clients call this whenever FCM hands them a new registration
     * token (first install, rotation, re-login). users.fcm_token holds a
     * single token per user, so the latest call wins.
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "fcm_token" => "required|string|max:500",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = auth()->user();
            $token = $validator->validated()["fcm_token"];

            // A token identifies a device. If another account last used this
            // device, drop the token from it so pushes meant for that account
            // don't land on this device.
            User::where("fcm_token", $token)->where("id", "!=", $user->id)->update(["fcm_token" => null]);

            $user->update(["fcm_token" => $token]);

            return ApiHelper::validResponse("FCM token updated successfully");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
