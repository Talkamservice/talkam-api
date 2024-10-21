<?php

namespace App\Services\Plan;

use App\Constants\Finance\Plan\PlanConstants;
use App\Models\Plan;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Database\Eloquent\Collection;

class PlanCheckService
{
    public User $user;
    public Plan $plan;
    public Collection $scopes;
    public string $key;

    public function __construct(int $user_id)
    {
        $this->user = UserService::getById($user_id);
        $this->init();
    }

    private function init()
    {
        $subscription = $this->user->currentSubscription;
        if (empty($subscription)) {
            $plan = Plan::where("name", "LIKE", "%" . PlanConstants::FREE_PLAN . "%")
                ->with("scopes")
                ->first();
        } else {
            $plan = $subscription->plan;
        }

        if (empty($plan)) {
            throw new PlanException("No plans set for this user");
        }
        $this->plan = $plan;
        $this->benefits = $plan->benefits;
    }

    public function setKey(string $value)
    {
        $this->key = $value;
        return $this;
    }

    public function check()
    {
        if (in_array($this->user->id, [1])) {
            return;
        }
        if ($this->key == PlanConstants::KEY_ACTIVE_WALLETS) {
            $this->checkForActiveWallets();
        } else if ($this->key == PlanConstants::KEY_SUB_ADMINS) {
            $this->checkForSubAdmins();
        } else if ($this->key == PlanConstants::KEY_TRANSACTIONS) {
            $this->checkForTransactions();
        } else if ($this->key == PlanConstants::KEY_QR_PAYMENT) {
            $this->checkForQrCodePayment();
        } else if ($this->key == PlanConstants::KEY_PVOUCHER) {
            $this->checkForParishVoucher();
        } else if ($this->key == PlanConstants::KEY_TRANSACTION_FEES) {
            return $this->checkForTransactionFees();
        } else if ($this->key == PlanConstants::KEY_WEB_PAYMENT_FEES) {
            return $this->checkForWebPaymentFees();
        } else if ($this->key == PlanConstants::KEY_TRANSACTION_FEES_CAP) {
            return $this->checkForTransactionFeesCap();
        } else if ($this->key == PlanConstants::KEY_WEB_PAYMENT_FEES_CAP) {
            return $this->checkForWebPaymentFeesCap();
        } else if ($this->key == PlanConstants::KEY_SUPPORT_PAYMENT_FEES) {
            return $this->checkForSupportPaymentFees();
        } else if ($this->key == PlanConstants::KEY_SUPPORT_PAYMENT_FEES_CAP) {
            return $this->checkForSupportPaymentFeesCap();
        } else {
            throw new PlanException("Invalid key");
        }
    }

    public function checkForActiveWallets()
    {
        $total_wallets = WalletService::getCoperateWallets($this->user, null, null);
        $total_wallets_count = $total_wallets->toQuery()->whereNotIn("type", [WalletConstants::PRIMARY])
            ->get()->count();
        $plan_wallets = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value ?? 0;

        if ($total_wallets_count >= floatval($plan_wallets)) {
            throw new PlanException("You have reached your limit for this plan. To add more wallets, kindly upgrade your plan.");
        }
    }

    public function checkForSubAdmins()
    {
        $total_subAdmins = ParishService::getSubAdmins($this->user->id)
            ->count();
        $plan_subAdmins = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value ?? 0;

        if ($total_subAdmins >= floatval($plan_subAdmins)) {
            throw new PlanException("You have reached your limit for this plan. To add more sub admin, kindly upgrade your plan.");
        }
    }

    public function checkForTransactions()
    {
        $total_trasanctions = $this->user->transactions()
            ->where("type", TransactionConstants::CREDIT)->count();

        $plan_transactions = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value ?? 0;

        if ($total_trasanctions >= floatval($plan_transactions)) {
            throw new PlanException("You cannot make payments to this account at the moment. Recipient account plan upgrade needed.");
        }
    }

    public function checkForTransactionFees()
    {
        $plan_transaction_fee = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value ?? 1.5;

        return $plan_transaction_fee;
    }

    public function checkForWebPaymentFees()
    {

        $plan_web_payment_fee = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value;

        return $plan_web_payment_fee;
    }


    public function checkForSupportPaymentFees()
    {
        $plan_support_pillar_payment_fee = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value;

        return $plan_support_pillar_payment_fee;
    }

    public function checkForTransactionFeesCap()
    {

        $plan_transaction_fee_cap = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value;

        return $plan_transaction_fee_cap;
    }

    public function checkForWebPaymentFeesCap()
    {

        $plan_web_payment_fee_cap = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value;

        return $plan_web_payment_fee_cap;
    }

    public function checkForSupportPaymentFeesCap()
    {
        $plan_support_payment_fee_cap = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value;

        return $plan_support_payment_fee_cap;
    }

    public function checkForQrCodePayment()
    {
        $plan_qrcode = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value ?? "no";

        if (strtolower($plan_qrcode) == "no") {
            throw new PlanException("You cannot receive payment via Qrcode for this plan. To receive payment via Qrcode, kindly upgrade your plan.");
        }
    }

    public function checkForParishVoucher()
    {
        $plan_qrcode = optional($this->benefits->where("key", $this->key)
            ->where("status", StatusConstants::ACTIVE)
            ->first())->value ?? "no";

        if (strtolower($plan_qrcode) == "no") {
            throw new PlanException("You cannot receive payment via PVoucher for this plan. To receive payment via PVoucher, kindly upgrade your plan.");
        }
    }
}
