<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use Exception;

class FlutterwaveService {
    
    private $env;
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
        $this->env = env("APP_ENV");
        $this->setBaseUrl();
        $this->setApiKey();
        $this->setHeaders();
        $this->client = $this->setClient();
    }

    public function setBaseUrl($url = null)
    {
        // Use Flutterwave's base URL based on environment
        // $this->base_url = $url ?? ($this->env == 'production' 
        //     ? 'https://api.flutterwave.com/v3' 
        //     : 'https://ravesandboxapi.flutterwave.com/v3');
        // return $this; // I want to get the key before i use this
    }

    public function setApiKey($key = null)
    {
        // Set the Flutterwave secret key
        $this->api_key = $key ?? config("services.flutterwave.secretKey");
        return $this;
    }

    public function setHeaders(?array $headers = [])
    {
        // Set headers for Flutterwave requests
        $this->headers = array_merge([
            'Authorization' => "Bearer {$this->api_key}",
            'Content-Type' => 'application/json',
        ], $headers);
    }

    public function setClient()
    {
        // Assuming GuzzleHttp is used for HTTP requests
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

    // Create a payment transaction using Flutterwave
    public function createTransaction()
    {
        try {
            $full_url = "{$this->base_url}/payments";
            $response = $this->client->postWithFormParams($full_url, $this->transaction_data);

            if ($response['status'] !== 'success') {
                throw new Exception($response['message']);
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    // Verify payment transaction using transaction ID
    public function verifyTransaction($transaction_id)
    {
        try {
            $full_url = "{$this->base_url}/transactions/{$transaction_id}/verify";
            $response = $this->client->get($full_url);

            if ($response['status'] !== 'success') {
                throw new Exception($response['message']);
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    // Refund a transaction
    public function refundTransaction($transaction_id, array $data)
    {
        try {
            $full_url = "{$this->base_url}/transactions/{$transaction_id}/refund";
            $response = $this->client->postWithFormParams($full_url, $data);

            if ($response['status'] !== 'success') {
                throw new Exception($response['message']);
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }
}
