<?php

namespace App\Services\Jobs\Webhook;

use App\Constants\ApiConstants;
use App\Constants\StatusConstants;
use App\Exceptions\Finance\Account\AccountException;
use App\Exceptions\Finance\BankAccountException;
use App\Exceptions\Finance\PendingDebitException;
use App\Exceptions\Finance\TransactionException;
use App\Exceptions\Finance\Wallet\WalletException;
use App\Exceptions\InvalidRequestException;
use App\Exceptions\UserException;
use App\Helpers\ApiHelper;
use App\Models\HookLog;
use App\Services\Finance\Account\SafeHaven\SafeHavenWebhookService;
use Exception;
use Illuminate\Validation\ValidationException;

class FlutterwaveWebhookJobService
{
    public array $data;
    public array $payload;
    public HookLog $webhook;
    public $safe_haven_webhook_service;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->payload = $this->data["content"];
        $this->webhook = HookLog::where("id", $this->data["id"])->first();
        $this->safe_haven_webhook_service = new SafeHavenWebhookService;
    }


    public function process()
    {
        try {
            $this->safe_haven_webhook_service->setPayload($this->payload)->handle();
            $response = ApiHelper::validData("Charge successful");
            $this->saveResponse($response, StatusConstants::SUCCESSFUL);
        } catch (ValidationException $e) {
            $response = ApiHelper::inputErrorData("The input is invalid", ApiConstants::VALIDATION_ERR_CODE, $e, $this->webhook->url);
        } catch (AccountException | UserException | InvalidRequestException | WalletException | TransactionException | BankAccountException | PendingDebitException $e) {
            $response = ApiHelper::problemData($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $e, $this->webhook->url);
            $this->saveResponse($response, StatusConstants::FAILED);
        } catch (Exception $e) {
            $response = ApiHelper::problemData("Something went wrong while trying to process the webhook", ApiConstants::SERVER_ERR_CODE, $e, $this->webhook->url);
            $this->saveResponse($response, StatusConstants::FAILED);
        }
    }

    public function saveResponse($response, $status)
    {
        $this->webhook->update([
            "status" => $status,
            "response" => $response,
            "processed_at" => now(),
        ]);
    }
}
