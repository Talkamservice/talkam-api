<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Exceptions\Payment\FlutterwaveException;
use App\Models\Plan;
use App\Services\General\Guzzle\GuzzleService;
use App\Services\System\ExceptionService;
use Exception;

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
            // dd( $response);
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

    public function getPlans()
    {
        try {
            $full_url = "{$this->base_url}/payment-plans";
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
                throw new FlutterwaveException($response["message"]["message"] ?? 'Unknown error occurred');
            }
            return $response['data'];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Transaction verification failed: ' . $e->getMessage());
        }
    }

    // Additive (v2 session bookings, mobile): Flutterwave Standard — a
    // hosted checkout page, returned as a link the client just opens (native
    // in-app browser/webview), instead of the inline SDK's client-side popup.
    public function createCheckoutLink(array $data)
    {
        try {
            $full_url = "{$this->base_url}/payments";
            $response = $this->client->post($full_url, $data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Unable to create checkout link');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Unable to create checkout link: ' . $e->getMessage());
        }
    }

    // Dedicated single-record lookup — NOT the same as GET /transactions
    // (a search/list endpoint whose index lagged behind a transaction that
    // had genuinely just succeeded, causing real false "not found" 400s
    // right after checkout). This endpoint reads the transaction directly
    // and is immediately consistent.
    public function verifyTransactionByReference($reference)
    {
        try {
            $full_url = "{$this->base_url}/transactions/verify_by_reference?tx_ref={$reference}";

            $response = $this->client->get($full_url);

            // A genuinely unknown reference 400s here — treated as "no
            // transaction found" (same shape as an empty search result)
            // rather than a hard failure, so the caller's retry/backoff
            // covers both "doesn't exist" and "not visible yet".
            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                return ["status" => "error", "data" => []];
            }

            return $response["data"];
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

    // Additive (v2 earnings): initiate a bank transfer (payout). No BVN
    // data is ever included.
    public function initiateTransfer(array $data)
    {
        try {
            $full_url = "{$this->base_url}/transfers";
            $response = $this->client->post($full_url, $data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Transfer initiation failed');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Transfer initiation failed: ' . $e->getMessage());
        }
    }

    // Additive (v2 earnings): transfer status lookup.
    public function verifyTransfer($transfer_id)
    {
        try {
            $full_url = "{$this->base_url}/transfers/{$transfer_id}";
            $response = $this->client->get($full_url);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Transfer lookup failed');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Transfer lookup failed: ' . $e->getMessage());
        }
    }

    // Additive (v2 saved cards): charge a previously tokenized card.
    public function chargeWithToken($token, array $data)
    {
        try {
            $full_url = "{$this->base_url}/tokenized-charges";
            $response = $this->client->post($full_url, array_merge(["token" => $token], $data));

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Tokenized charge failed');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Tokenized charge failed: ' . $e->getMessage());
        }
    }

    // Additive (v2 B2B §11): create a dedicated NGN virtual account. The caller
    // passes the BVN/NIN in $data (required for a permanent account); it is sent to
    // Flutterwave only and is NEVER persisted by TalkAM.
    public function createVirtualAccount(array $data)
    {
        try {
            $full_url = "{$this->base_url}/virtual-account-numbers";
            $response = $this->client->post($full_url, $data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Virtual account creation failed');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Virtual account creation failed: ' . $e->getMessage());
        }
    }

    // Additive (v2 therapist onboarding): Flutterwave bank list.
    public function getBanks($country = "NG")
    {
        try {
            $full_url = "{$this->base_url}/banks/{$country}";
            $response = $this->client->get($full_url);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Unable to retrieve bank list');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Unable to retrieve bank list: ' . $e->getMessage());
        }
    }

    // Additive (v2 therapist onboarding): resolves an account number to the
    // registered account name. No BVN data is requested or stored.
    public function resolveAccountNumber($bank_code, $account_number)
    {
        try {
            $full_url = "{$this->base_url}/accounts/resolve";
            $response = $this->client->post($full_url, [
                "account_number" => $account_number,
                "account_bank" => $bank_code,
            ]);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new FlutterwaveException($response["message"]["message"] ?? 'Unable to resolve account');
            }

            return $response["data"]["data"] ?? $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
            throw new FlutterwaveException('Account resolution failed: ' . $e->getMessage());
        }
    }
}
