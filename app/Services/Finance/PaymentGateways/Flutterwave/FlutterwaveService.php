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

    public function createCustomer()
    {
        try {
            $full_url = "https://api-sit.flutterwave.cloud/developersandbox/customers";
            $data = $this->customer_data;
            // dd($data);
            $response = $this->client->postWithFormParams($full_url, $data);
            dd($response);
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["error"]["message"] ?? null);
            }
            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function setPlanData(array $plan_data = [])
    {
        $this->plan_data = $plan_data;
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
            throw new ModelNotFoundException("Plan member not found");
        }
        return $plan;
    }


    public  function updateFlutterwavePrices($plan)
    {
        $durations = $plan->durations()->whereNotNull("flutterwave_price_id")->get();

        foreach ($durations as $key => $duration) {
            $this->setPriceData([
                'currency' => 'USD',
                'amount' => floatval((new PlanService)->parsePlanPrice($duration)),
                'plan' => $plan->name,
            ])->createTransaction(); // Adjusted for Flutterwave price update logic
        }
    }
}
