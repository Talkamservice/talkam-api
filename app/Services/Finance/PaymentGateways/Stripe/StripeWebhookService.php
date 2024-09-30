<?php

namespace App\Services\Finance\PaymentGateways\Stripe;

use App\Models\User;


class StripeWebhookService
{
    public array $payload;
    public $event;
    public User $user;
    public $model;

    public function setPayload(array $value)
    {
        $this->payload = $value;
        return $this;
    }

    public function handle()
    {
       return $this->handleActionsByEvents();
    }

    public function handleActionsByEvents()
    {
        $payload = $this->payload;
        if (in_array($payload["type"] ?? null, ["payment_intent.succeeded"])) {
            return $this->handlePaymentIntentSucceeded($payload);
        }
        if (in_array($payload["type"] ?? null, ["invoice.payment_succeeded"])) {
            return $this->handleInvoicePaymentSucceeded($payload);
        }
        if (in_array($payload["type"] ?? null, ["customer.subscription.updated", "customer.subscription.deleted"])) {
            return $this->handleSubscriptionUpdate($payload);
        }
    }

    public function handlePaymentIntentSucceeded($payload)
    {
        return (new StripePaymentWebhookService)
            ->setPayload($payload)->handle();
    }

    public function handleInvoicePaymentSucceeded($payload)
    {
        return (new StripeInvoicePaymentWebhookService)
            ->setPayload($payload)->handle();
    }

    public function handleSubscriptionUpdate($payload)
    {
        return (new StripeSubscriptionWebhookService)
            ->setPayload($payload)->handle();
    }
}
