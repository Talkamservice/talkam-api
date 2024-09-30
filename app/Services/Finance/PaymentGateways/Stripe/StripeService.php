<?php

namespace App\Services\Finance\PaymentGateways\Stripe;

use App\Constants\General\ApiConstants;
use App\Exceptions\Finance\StripeException;
use App\Services\General\Guzzle\GuzzleService;
use App\Services\System\ExceptionService;
use Exception;

class StripeService
{
    private $env;
    public $base_url;
    public $api_key;
    public array $headers;
    public $client;
    public $customer_data;
    public $subscription_data;
    public $payment_intent_data;
    public $payment_method_data;
    public $currency;
    public $price_data;
    public $payment_method_to_consumer_data;

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
        $this->base_url = config("services.stripe.base_url");
        return $this;
    }

    public function setApiKey($key = null)
    {
        $this->api_key = config("services.stripe.secret_key");
        return $this;
    }

    public function setHeaders(?array $headers = [])
    {
        $this->headers = array_merge([
            'Authorization' => "Bearer $this->api_key",
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $headers);
    }

    public function setClient()
    {
        $this->client = new GuzzleService($this->headers);
        return $this->client;
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
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function createSubscription()
    {
        try {
            $full_url = $this->base_url . "/subscriptions";
            $response = $this->client->postWithFormParams($full_url, $this->subscription_data);

            logger("Subscription", [
                "url" => $full_url,
                "headers" => $this->headers,
                "response" => $response,
            ]);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new StripeException($response["message"]["error"]["message"] ?? null);
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
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function createPaymentMethod()
    {
        try {
            $full_url = $this->base_url . "/payment_methods";
            $response = $this->client->postWithFormParams($full_url, $this->payment_method_data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }

    public function attachPaymentMethodToCustomer($payment_method_id, array $data)
    {
        try {
            $full_url = $this->base_url . "/payment_methods/$payment_method_id/attach";
            $response = $this->client->postWithFormParams($full_url, $data);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }


    public function createPaymentIntent()
    {
        try {
            $full_url = $this->base_url . "/payment_intents";
            $response = $this->client->postWithFormParams($full_url, $this->payment_intent_data);

            logger("Subscription", [
                "url" => $full_url,
                "headers" => $this->headers,
                "response" => $response,
            ]);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new StripeException($response["message"]["error"]["message"] ?? null);
            }

            return $response["data"];
        } catch (Exception $e) {
            ExceptionService::logAndBroadcast($e);
        }
    }
}
