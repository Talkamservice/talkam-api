<?php

namespace App\Http\Controllers\Api\V2\Messaging;

use App\Constants\General\ApiConstants;
use App\Constants\Messaging\MessagingV2Constants;
use App\Events\Messaging\UserPresenceChanged;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Messaging\V2\PresenceService;
use App\Services\User\PrivacySettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class PresenceController extends Controller
{
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "status" => ["required", Rule::in(MessagingV2Constants::PRESENCE_STATES)],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = auth()->user();

            // Suppressed when §09 activity_status is off — nothing is stored
            // or broadcast. Stored status is a short-TTL cache entry only.
            if (PrivacySettingService::forUser($user)["activity_status"]) {
                PresenceService::set($user->id, $request->status);
                event(new UserPresenceChanged($user->id, $request->status));
            }

            return ApiHelper::validResponse("Presence updated");
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show(int $user)
    {
        try {
            if (! PresenceService::canView(auth()->user(), $user)) {
                return ApiHelper::problemResponse("You cannot view this user's presence", ApiConstants::FORBIDDEN_ERR_CODE);
            }

            return ApiHelper::validResponse("Presence fetched", [
                "user_id" => $user,
                "status" => PresenceService::get($user),
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
