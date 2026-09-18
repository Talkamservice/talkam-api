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
 * AV provider (Agora Notifications) call-state webhook. Only this
 * controller and SessionCallService may reference the provider.
 *
 * Real Agora payload shape (NOT a flat {event, channel_ref} — the account
 * this was originally built against wasn't live yet, so that was a
 * placeholder guess):
 *   { "noticeId": "...", "productId": 1, "eventType": 102,
 *     "notifyMs": 1234567890, "payload": { "channelName": "...", "ts": ..., "lastUid": ... } }
 * https://docs.agora.io/en/video-calling/channel-management-api/webhook/channel-event-type
 */
class AvProviderWebhookController extends Controller
{
    /** productId for the RTC (Video/Voice Calling) product. */
    private const PRODUCT_RTC = 1;

    /** eventType: the last user left and the channel was destroyed. */
    private const EVENT_CHANNEL_DESTROY = 102;

    public function handle(Request $request)
    {
        try {
            $call_service = app(SessionCallService::class);

            if (!$call_service->verifyWebhook($request)) {
                return ApiHelper::problemResponse("Invalid webhook signature", ApiConstants::AUTH_ERR_CODE, null, null);
            }

            $product_id = (int) $request->input("productId");
            $event_type = (int) $request->input("eventType");
            $channel_name = $request->input("payload.channelName");

            if ($product_id === self::PRODUCT_RTC && $event_type === self::EVENT_CHANNEL_DESTROY && !empty($channel_name)) {
                (new SessionLifecycleService)->handleRoomClosed($channel_name);
            }

            return ApiHelper::validResponse("Webhook processed");
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
