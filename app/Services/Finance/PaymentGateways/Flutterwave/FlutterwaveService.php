<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\Plan;
use App\Services\Finance\Plan\PlanService;
use App\Services\Finance\Subscription\SubscriptionService;
use App\Services\General\Guzzle\GuzzleService;
use App\Services\System\ExceptionService;
use Exception;
use Illuminate\Http\Request;
use Symfony\Contracts\Service\Attribute\SubscribedService;

class FlutterwaveService
{
    protected $base_url;
    protected $api_key;
    protected array $headers;
    protected $client;
    public $payment_intent_data;
    protected $customer_data;
    protected $plan_data;
    protected $price_data;
    protected $flutterwave_plan_id;
    public $subscription_data;
    protected $payment_method_data;
    protected $payment_method_to_consumer_data;

    public function __construct()
    {
        $this->setBaseUrl();
        $this->setApiKey();
        $this->setHeaders();
        $this->client = $this->setClient();
    }

    public function setBaseUrl()
    {
        $this->base_url = env("FLW_BASE_URL") ?? config("services.flutterwave.baseUrl");
        return $this;
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

    public function setPaymentIntentData(array $value)
    {
        $this->payment_intent_data = $value;
        return $this;
    }

    public function setSubscriptionData(array $value = [])
    {
        $this->subscription_data = $value;
        return $this;
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

    public function setPlanData(array $plan_data = [])
    {
        $this->plan_data = $plan_data;
        return $this;
    }

    public function createCustomer()
    {
        try {
            $full_url = $this->base_url . "/customers";
            $response = $this->client->post($full_url, $this->customer_data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    // Creates a payment transaction on Flutterwave
    public function createPlan()
    {
        try {
            $full_url = "{$this->base_url}/payment-plans";
            $response = $this->client->post($full_url, $this->plan_data);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            // throw new FlutterwaveException('Plan creation failed: ' . $e->getMessage());
        }
    }

    // update a payment transaction on Flutterwave
    public function updatePlan($flutterwave_plan_id)
    { 
        try {
            $full_url = "{$this->base_url}/payment-plans/$flutterwave_plan_id";
            // dd($full_url);
            $response = $this->client->put($full_url, $this->plan_data);
            // dd($response);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            // throw new FlutterwaveException('Plan update failed: ' . $e->getMessage());
        }
    }

    // Cancel a Flutterwave payment plan
    public function cancelPlan($flutterwave_plan_id)
    {
        try {
            $full_url = "{$this->base_url}/payment-plans/$flutterwave_plan_id/cancel";
            $response = $this->client->put($full_url);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred during plan cancellation');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            // throw new FlutterwaveException('Plan cancellation failed: ' . $e->getMessage());
        }
    }

    public function getPlan($flutterwave_plan_id)
    {
        try {
            $full_url = "{$this->base_url}/payment-plans/$flutterwave_plan_id";
            $response = $this->client->get($full_url);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred during plan cancellation');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            // throw new FlutterwaveException('Unable to get plan: ' . $e->getMessage());
        }
    }

    // Verifies the status of a transaction by transaction ID
    public function verifyTransaction($transaction_id)
    {
        try {
            $full_url = "{$this->base_url}/transactions/{$transaction_id}/verify";
            $response = $this->client->get($full_url);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Transaction verification failed: ' . $e->getMessage());
        }
    }

    // Refunds a transaction by transaction ID
    public function refundTransaction($transaction_id, array $data)
    {
        try {
            $full_url = "{$this->base_url}/transactions/{$transaction_id}/refund";
            $response = $this->client->postWithFormParams($full_url, $data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Refund failed');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Refund process failed: ' . $e->getMessage());
        }
    }

    public function getPlanById($key, $column = "id")
    {
        $plan = Plan::where($column, $key)->first();
        if (empty($plan)) {
            throw new ModelNotFoundException("Plan not found");
        }
        return $plan;
    }

    public function createSubscription()
{
    try {
        $full_url = $this->base_url . "/payments";
        $response = $this->client->post($full_url, $this->subscription_data); // Use the passed $subscription_data

        logger("Subscription", [
            "url" => $full_url,
            "headers" => $this->headers,
            "response" => $response,
        ]);

        if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
            throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
        }

        return $response["data"];
    } catch (Exception $e) {
        ExceptionService::logAndBroadcast($e);
    }
}


    public function cancelSubscription($subscription_id)
    {
        try {
            $full_url = $this->base_url . "/subscriptions/$subscription_id";
            $response = $this->client->delete($full_url);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }
}
