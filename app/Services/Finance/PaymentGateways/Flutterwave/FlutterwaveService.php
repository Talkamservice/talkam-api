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

    public function __construct()
    {
        $this->setBaseUrl();
        $this->setApiKey();
        $this->setHeaders();
        $this->client = $this->setClient();
    }

    // Sets the Flutterwave base URL from the environment variables
    public function setBaseUrl()
    {
        $this->base_url = env("FLW_BASE_URL");
        return $this;
    }

    // Sets the API key, allows for optional overriding
    public function setApiKey($key = null)
    {
        $this->api_key = $key ?? config("services.flutterwave.secretKey");
        return $this;
    }

    // Sets request headers, merges any additional headers provided
    public function setHeaders(?array $headers = [])
    {
        $this->headers = array_merge([
            'Authorization' => "Bearer {$this->api_key}",
            'Content-Type' => 'application/json',
        ], $headers);
    }

    // Instantiates the Guzzle client with the set headers
    public function setClient()
    {
        return new GuzzleService($this->headers);
    }

    public function setPaymentIntentData(array $value)
    {
        $this->payment_intent_data = $value;
        return $this;
    }

    // Sets customer data for transactions
    public function setCustomerData(array $value)
    {
        $this->customer_data = $value;
        return $this;
    }

    // Sets price data for transactions
    public function setPriceData(array $value)
    {
        $this->price_data = $value;
        return $this;
    }

    // public function createCustomer()
    // {
    //     try {
    //         $full_url = "https://api-sit.flutterwave.cloud/developersandbox/customers";
    //         $data = $this->customer_data;
    //         // dd($data);
    //         $response = $this->client->postWithFormParams($full_url, $data);
    //         dd($response);
    //         if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
    //             throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
    //         }
    //         return $response["data"];
    //     } catch (Exception $e) {
    //         ExceptionService::logAndBroadcast($e);
    //     }
    // }

    public function setPlanData(array $plan_data = [])
    {
        $this->plan_data = $plan_data;
        return $this;
    }

    public function setFlutterwavePlanId($plan_id)
{
    // Retrieve the specific plan by ID
    $plan = Plan::findOrFail($plan_id); // Assuming you're using the `id` field to identify the plan
    
    // Extract the Flutterwave plan ID from the plan durations (or any other structure)
    $this->flutterwave_plan_id = $plan->durations->first()->flutterwave_plan_id ?? null;

    // Ensure the plan ID is set correctly
    if (empty($this->flutterwave_plan_id)) {
        throw new FlutterwaveException('No Flutterwave plan ID found.');
    }
dd($this);
    return $this;
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
            throw new FlutterwaveException('Transaction creation failed: ' . $e->getMessage());
        }
    }

    // update a payment transaction on Flutterwave
    public function updatePlan($plan_id)
    {
        $this->setFlutterwavePlanId($plan_id);
        try {
            $full_url = "{$this->base_url}/payment-plans/{$this->flutterwave_plan_id}";
            dd($full_url);
            $response = $this->client->post($full_url, $this->plan_data);
            // dd($response);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Plan update failed: ' . $e->getMessage());
        }
    }

    // Cancel a Flutterwave payment plan
    public function cancelPlan()
    {
        try {
            $full_url = "{$this->base_url}/payment-plans/{$this->flutterwave_plan_id}/cancel";
            $response = $this->client->put($full_url);
            // dd($response);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred during plan cancellation');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Plan cancellation failed: ' . $e->getMessage());
        }
    }

    public function getPlan()
    {
        try {
            $full_url = "{$this->base_url}/payment-plans/{$this->flutterwave_plan_id}";
            $response = $this->client->get($full_url);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? 'Unknown error occurred during plan cancellation');
            }

            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Unable to get plan: ' . $e->getMessage());
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
}
