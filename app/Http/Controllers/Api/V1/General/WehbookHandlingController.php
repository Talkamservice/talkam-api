<?php

namespace App\Http\Controllers\Api\V1\General;

use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\General\Webhook\WebhookService;
use App\Services\User\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WehbookHandlingController extends Controller
{
    public $webhook_service;

    public function __construct()
    {
        $this->webhook_service = new WebhookService;
    }

    public function handleWebhook(Request $request)
    {
        Log::info("Webhook request received", $request->all());

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 400,
                'success' => false,
                'message' => 'Invalid JSON',
                'data' => [
                    "error" => json_last_error_msg()
                ]
            ], 400);
        }

        return $this->saveWebhook($request);
    }

    public function saveWebhook(Request $request)
    {
        try {
            $user = $this->getUser($request);
            $payload = $request->all();
            $webhook = $this->webhook_service->create([
                "source" => PaymentConstants::FLUTTERWAVE,
                "event" => $payload["event"] ?? null,
                "headers" => $request->header(),
                "content" => $payload,
                "url" => env("APP_URL") . "/webhook/verifications",
                "delay" => "10",
                "user_id" => $user["user"]?->id,
                "sender_name" => $user["name"] ?? null
            ]);

            $this->webhook_service->dispatch($webhook);
            return ApiHelper::validResponse("Hook Received");
        } catch (ValidationException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function getUser(Request $request)
    {
        $payload = $request->all();
        $type = $payload["event"] ?? null;

        if (in_array($type, ["charge.completed"])) {
            $user = UserService::getById($payload["data"]["customer"]["email"] ?? null, "email");
            return [
                "user" => $user,
                "name" => $user?->getName()
            ];
        } else {
            return [
                "user" => sudo(),
                "name" => sudo()?->getName()
            ];
        }
    }
}
