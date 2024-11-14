<?php

namespace App\Services\Jobs\Webhook;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Models\HookLog;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveWebhookService;
use Exception;
use Illuminate\Validation\ValidationException;

class FlutterwaveWebhookJobService
{
    public array $data;
    public array $payload;
    public HookLog $webhook;
    public $flutterwave_webhook_service;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->payload = $this->data["content"];
        $this->webhook = HookLog::where("id", $this->data["id"])->first();
        $this->flutterwave_webhook_service = new FlutterwaveWebhookService;
    }


    public function process()
    {
        try {
            $this->flutterwave_webhook_service->setPayload($this->payload)->handle();
            $response = ApiHelper::validData("Charge successful");
            $this->saveResponse($response, StatusConstants::SUCCESSFUL);
        } catch (ValidationException $e) {
            $response = ApiHelper::inputErrorData("The input is invalid", ApiConstants::VALIDATION_ERR_CODE, $e, $this->webhook->url);
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            $response = ApiHelper::problemData($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $e, $this->webhook->url);
            $this->saveResponse($response, StatusConstants::FAILED);
        } catch (Exception $e) {
            $response = ApiHelper::problemData("Something went wrong while trying to process the webhook", ApiConstants::SERVER_ERR_CODE, $e, $this->webhook->url);
            $this->saveResponse($response, StatusConstants::FAILED);
        }
    }

    public function saveResponse($response, $status)
    {
        logger("Webhook response", [$response, $status]);
        $this->webhook->update([
            "status" => $status,
            "response" => $response,
            "processed_at" => now(),
        ]);
    }
}
