<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\ApiConstants;
use App\Exceptions\Payment\FlutterwaveException;
use App\Services\General\Guzzle\GuzzleService;
use App\Services\System\ExceptionService;
use Exception;

class FlutterwaveService
{

    public $base_url;
    public $api_key;
    public array $headers;
    public $client;
    public $customer_data;
    public $transaction_data;
    public $currency;
    public $price_data;

    public function __construct()
    {
        $this->setBaseUrl();
        $this->setApiKey();
        $this->setHeaders();
        $this->client = $this->setClient();
    }

    public function setBaseUrl()
    {
        $this->base_url = env("FLW_BASE_URL");
    }

    public function setApiKey($key = null)
    {
        $this->api_key = $key ?? config("services.flutterwave.secretKey");
        return $this;
    }

    public function setHeaders(?array $headers = [])
    {
        $this->headers = array_merge([
            'Authorization' => "Bearer {$this->api_key}",
            'Content-Type' => 'application/json',
        ], $headers);
    }

    public function setClient()
    {
        return new GuzzleService($this->headers);
    }

    public function setCustomerData(array $value)
    {
        $this->customer_data = $value;
        return $this;
    }

    public function setTransactionData(array $value)
    {
        $this->transaction_data = $value;
        return $this;
    }

    public function createTransaction()
    {
        try {
            $full_url = "{$this->base_url}/payments";
            $response = $this->client->postWithFormParams($full_url, $this->transaction_data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function verifyTransaction($transaction_id)
    {
        try {
            $full_url = "{$this->base_url}/transactions/{$transaction_id}/verify";
            $response = $this->client->get($full_url);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function refundTransaction($transaction_id, array $data)
    {
        try {
            $full_url = "{$this->base_url}/transactions/{$transaction_id}/refund";
            $response = $this->client->postWithFormParams($full_url, $data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }
}
