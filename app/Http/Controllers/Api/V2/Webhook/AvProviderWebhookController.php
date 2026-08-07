<?php

namespace App\Http\Controllers\Api\V2\Webhook;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\SessionCallService;
use App\Services\Therapist\SessionLifecycleService;
use Illuminate\Http\Request;
use Exception;

/**
 * AV provider (Agora) call-state webhook. Only this controller and
 * SessionCallService may reference the provider.
 */
class AvProviderWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $call_service = app(SessionCallService::class);

            if (!$call_service->verifyWebhook($request)) {
                return ApiHelper::problemResponse("Invalid webhook signature", ApiConstants::AUTH_ERR_CODE, null, null);
            }

            $event = $request->input("event");
            if (in_array($event, ["room_closed", "channel_destroyed"])) {
                (new SessionLifecycleService)->handleRoomClosed($request->input("channel_ref"));
            }

            return ApiHelper::validResponse("Webhook processed");
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
