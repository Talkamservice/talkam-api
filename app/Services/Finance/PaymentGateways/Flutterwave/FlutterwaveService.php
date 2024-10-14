<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\ApiConstants;
use App\Models\Plan;
use App\Services\General\Guzzle\GuzzleService;
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

    public function setPriceData(array $value)
    {
        $this->price_data = $value;
        return $this;
    }

    public function setSubscriptionData(array $value)
    {
        $this->subscription_data = $value;
        return $this;
    }

    public function setPaymentIntentData(array $value)
    {
        $this->payment_intent_data = $value;
        return $this;
    }

    public function setPaymentMethodData(array $value)
    {
        $this->payment_method_data = $value;
        return $this;
    }

    public function setAttachPaymentMethodToConsumerData(array $value)
    {
        $this->payment_method_to_consumer_data = $value;
        return $this;
    }

    public function createCustomer()
    {
        try {
            $full_url = $this->base_url . "/customers";
            $response = $this->client->post($full_url, $this->customer_data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function createPrice()
    {
        try {
            $full_url = $this->base_url . "/prices";
            $response = $this->client->postWithFormParams($full_url, $this->price_data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function updatePrice($price_id)
    {
        try {
            $full_url = $this->base_url . "/prices/$price_id";
            $response = $this->client->post($full_url, $this->price_data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new Exception($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    /**
     * Process payment through Flutterwave.
     *
     * @param Plan $plan
     * @param float $amount
     * @return void
     */
    public function processFlutterwavePayment(Plan $plan, $amount)
    {
        $paymentData = [
            'tx_ref' => uniqid('trx_'), // Unique transaction reference
            'amount' => $amount,
            'currency' => 'USD',
            'payment_options' => 'card', // You can allow other options like bank, mobilemoney, etc.
            'redirect_url' => route('payment.callback'), // Your payment callback route
            'customer' => [
                'email' => auth()->user()->email,
                'name' => auth()->user()->name,
            ],
            'meta' => [
                'plan_id' => $plan->id, // Store the plan id for reference in callback
            ],
            'customizations' => [
                'title' => 'Plan Payment',
                'description' => 'Payment for ' . $plan->name,
                'logo' => asset('path_to_logo'), // Your logo path
            ]
        ];

        // Initialize the Flutterwave payment
        $this->rave->initializePayment($paymentData);
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
