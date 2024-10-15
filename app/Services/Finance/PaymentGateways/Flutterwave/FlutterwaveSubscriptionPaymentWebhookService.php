<?php

namespace App\Services\Finance\PaymentGateways\Flutterwave;

use App\Constants\Finance\PaymentConstants;
use App\Constants\Finance\TransactionActivityConstants;
use App\Constants\Finance\TransactionConstants;
use App\Constants\Finance\WalletConstants;
use App\Constants\Snitch\SnitchActivityConstants;
use App\Constants\Snitch\SnitchConstants;
use App\Constants\StatusConstants;
use App\Exceptions\Finance\Account\AccountException;
use App\Exceptions\Finance\TransactionException;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Finance\Account\Bank\BankAccountService;
use App\Services\Finance\Deposit\DepositLevelService;
use App\Services\Finance\Transaction\TransactionService;
use App\Services\Finance\Wallet\Api\ApiWalletService;
use App\Services\Finance\Wallet\WalletService;
use App\Services\Finance\Payment\PaymentService;
use App\Services\Snitch\SnitchService;
use App\Services\System\Notifications\FcmPushNotificationService;
use App\Services\System\Settings\SettingService;
use App\Services\System\TransactionService as SystemTransactionService;
use Illuminate\Support\Facades\Log;

class FlutterwaveSubscriptionPaymentWebhookService
{
    public array $payload;
    public $event;
    public User $user;
    public BankAccount $bank_account;
    public $model;

    public function setPayload(array $value)
    {
        $this->payload = $value;
        return $this;
    }

    public function handle()
    {
        $this->parsePayload();
        $this->actionHandler();
    }

    public function parsePayload()
    {
        $payload = $this->payload;
        if (empty($payload)) {
            throw new AccountException("Charge data not set!");
        }

        //Check if is sweep transfer
        if ($this->checkSweepPaymentReferencePrefix($payload)) {
            throw new AccountException("This payment is a sweep payment!");
        }

        $this->bank_account = $this->setBankAccount($payload);
        $this->model = $this->bank_account->isCoperateBankAccount();
        $this->user = $this->setUser();
    }

    function checkSweepPaymentReferencePrefix($payload) {
        $paymentReference = $payload['data']['paymentReference'];
        if (substr($paymentReference, 0, 5) === 'ACPF_') {
            return true;
        } else {
            return false;
        }
    }

    public function setBankAccount($payload)
    {
        $bank_account = BankAccountService::getById($payload["data"]["creditAccountNumber"], "account_number");
        return $this->bank_account = $bank_account;
    }

    public function setUser()
    {
        return $this->user = $this->bank_account->owner;
    }

    private function actionHandler()
    {
        $this->verifyTransactionStatus($this->payload);
        $this->handleSuccess($this->payload);
    }

    private function verifyTransactionStatus($payload)
    {
        $transaction = Transaction::where("reference", $payload["data"]["sessionId"] ?? null)->first();

        if (!empty($transaction) && ($transaction->status == StatusConstants::COMPLETED)) {
            throw new TransactionException("You cannot process a completed transaction");
        }
    }

    private function handleSuccess($payload)
    {
        if (!empty($this->model)) {
            $this->processMerchantTransaction($payload);
        } else {
            $this->processUserTransaction($payload);
        }
    }

    public function processUserTransaction($payload)
    {
        $wallet = WalletService::getByWalletType($this->user->id, WalletConstants::PRIMARY);
        $amount = floatval($payload["data"]["amount"]);

        $old_wallet = $wallet;
        $old_wallet_balance = TransactionService::oldWalletBalance($wallet);
        $wallet = ApiWalletService::credit($wallet, $amount);
        $new_wallet_balance = TransactionService::newWalletBalance($wallet->refresh());

        $transaction = TransactionService::create([
            "user_id" => $this->user->id,
            "currency_id" => $wallet->currency_id,
            "wallet_id" => $wallet->id,
            "amount" => $amount,
            "fees" => $payload["data"]["fees"],
            "description" => "Transfer from " . $payload["data"]["debitAccountName"],
            "reference" => $payload["data"]["sessionId"],
            "activity" => TransactionActivityConstants::FUNDS_RECEIVED_VIRTUAL_ACCOUNT,
            "category" => TransactionConstants::BANK_TRANSFER_DEPOSIT,
            "type" => TransactionConstants::CREDIT,
            "source_provider" => PaymentConstants::GATEWAY_SAFE_HAVEN,
            "sender_name" => $payload["data"]["debitAccountName"],
            "transaction_type" => TransactionConstants::EXTERNAL_TRANSACTION,
            "action" => TransactionConstants::BANK_DEPOSIT,
            "status" => StatusConstants::COMPLETED,
            "prev_wallet_balance" => $old_wallet_balance["balance"],
            "new_wallet_balance" => $new_wallet_balance["balance"],
            "batch_no" => TransactionService::generateBatchNo(10),
            "logs" => json_encode($payload["data"]),
            "metadata" => json_encode([
                "wallet" => [
                    "old" => $old_wallet_balance,
                    "new" => $new_wallet_balance
                ]
            ])
        ]);

        PaymentService::create([
            "user_id" => $this->user->id,
            "currency_id" => $transaction->currency_id,
            "transaction_id" => $transaction->id,
            "reference" => $transaction->reference,
            "amount" => $amount,
            "fee" => $transaction->fees ?? 0,
            "metadata" => json_encode($payload["data"]),
            "activity" => TransactionActivityConstants::FUNDS_RECEIVED_VIRTUAL_ACCOUNT,
            "gateway" => PaymentConstants::GATEWAY_SQUAD,
            "action" => PaymentConstants::DEPOSIT,
            "status" => StatusConstants::COMPLETED
        ]);

        (new SnitchService)
            ->setEvent(SnitchConstants::EVENT_FUNDED)
            ->setTitle("Funding of virtual account")
            ->setDescription(SnitchConstants::parseName($this->user) . " just funded his wallet with " . format_money($amount) . " from " . PaymentConstants::GATEWAY_SQUAD)
            ->setType(SnitchConstants::API_URL_TYPE)
            ->setActivity(SnitchActivityConstants::FUNDING_OF_WALLET_WITH_GATEWAY)
            ->setModel(Wallet::class, $wallet->id)
            ->setUser($this->user->id)
            ->setData([
                "wallet" => $wallet->toArray(),
                "transaction" => $transaction->toArray()
            ], [
                "wallet" => $old_wallet->toArray(),
            ])
            ->setMetadata([
                "transaction_ref" => $transaction->reference,
                "user" => [
                    "puid" => $this->user->puid,
                    "first_name" => $this->user->first_name,
                    "middle_name" => $this->user->middle_name,
                    "last_name" => $this->user->last_name,
                    "email" => $this->user->email,
                ],
                "amount" => $amount,
                "wallet" => [
                    "old" => $old_wallet_balance,
                    "new" => $new_wallet_balance
                ]
            ])
            ->setUrl(SnitchConstants::parseUrl("safe-haven/webhook/verifications"))
            ->log();

        (new FcmPushNotificationService)
            ->setTitle("Receipt of fund")
            ->setBody("You have just received a payment of " . format_money($amount) . " from " . $transaction->sender_name)
            ->setType("Funding")
            ->setMetadata([
                "type" => "Funding",
                "transaction_id" => $transaction->id,
            ])
            ->byUserId($this->user->id)
            ->initiate();

        clearPendingDebits($wallet);

        if (!in_array($wallet->sub_type, globalSetting()->electronic_transfer_levy_exception)) {
            if ($amount >= globalSetting()->deposit_charge_req) {
                $this->clearServiceCharge($transaction);
            }
        }

        Log::info("If you can see this message and you do not see any newly created transaction for the user with userId " . $this->user->id . " and transaction reference " . $transaction->reference . " just know that the webhook reached that section of your code");
    }

    public function processMerchantTransaction($payload)
    {
        $wallet = $this->bank_account->wallet;

        if (empty($wallet)) {
            $wallet = WalletService::getCoperateWalletByType($this->model, WalletConstants::PRIMARY);
        }

        $amount = floatval($payload["data"]["amount"]);
        $fees = $this->parseMerchantFee($amount);

        $old_wallet = $wallet;
        $old_wallet_balance = TransactionService::oldWalletBalance($wallet);
        $wallet = ApiWalletService::credit($wallet, $amount);
        $new_wallet_balance = TransactionService::newWalletBalance($wallet->refresh());

        $transaction = TransactionService::create([
            "user_id" => $this->user->id,
            "currency_id" => $wallet->currency_id,
            "wallet_id" => $wallet->id,
            modelKey($this->model) => $this->model->id,
            "amount" => $amount,
            "fees" => $fees ?? 0,
            "description" => "Transfer from " . $payload["data"]["debitAccountName"],
            "reference" => $payload["data"]["sessionId"],
            "activity" => TransactionActivityConstants::FUNDS_RECEIVED_VIRTUAL_ACCOUNT,
            "category" => TransactionConstants::BANK_TRANSFER_DEPOSIT,
            "type" => TransactionConstants::CREDIT,
            "source_provider" => PaymentConstants::GATEWAY_SAFE_HAVEN,
            "sender_name" => $payload["data"]["debitAccountName"],
            "transaction_type" => TransactionConstants::EXTERNAL_TRANSACTION,
            "action" => TransactionConstants::BANK_DEPOSIT,
            "status" => StatusConstants::COMPLETED,
            "prev_wallet_balance" => $old_wallet_balance["balance"],
            "new_wallet_balance" => $new_wallet_balance["balance"],
            "batch_no" => TransactionService::generateBatchNo(10),
            "logs" => json_encode($payload["data"]),
            "metadata" => json_encode([
                "wallet" => [
                    "old" => $old_wallet_balance,
                    "new" => $new_wallet_balance
                ]
            ])
        ]);

        PaymentService::create([
            "user_id" => $this->user->id,
            "currency_id" => $transaction->currency_id,
            "transaction_id" => $transaction->id,
            modelKey($this->model) => $this->model->id,
            "reference" => $transaction->reference,
            "amount" => $amount,
            "fee" => $transaction->fees ?? 0,
            "metadata" => json_encode($payload["data"]),
            "activity" => TransactionActivityConstants::FUNDS_RECEIVED_VIRTUAL_ACCOUNT,
            "gateway" => PaymentConstants::GATEWAY_SQUAD,
            "action" => PaymentConstants::DEPOSIT,
            "status" => StatusConstants::COMPLETED
        ]);

        (new SnitchService)
            ->setEvent(SnitchConstants::EVENT_FUNDED)
            ->setTitle("Funding of virtual account")
            ->setDescription(SnitchConstants::parseName($this->user) . " just funded his wallet with " . format_money($amount) . " from " . PaymentConstants::GATEWAY_SQUAD)
            ->setType(SnitchConstants::API_URL_TYPE)
            ->setActivity(SnitchActivityConstants::FUNDING_OF_WALLET_WITH_GATEWAY)
            ->setModel(Wallet::class, $wallet->id)
            ->setUser($this->user->id)
            ->setData([
                "wallet" => $wallet->toArray(),
                "transaction" => $transaction->toArray()
            ], [
                "wallet" => $old_wallet->toArray(),
            ])
            ->setMetadata([
                "transaction_ref" => $transaction->reference,
                "user" => [
                    "puid" => $this->user->puid,
                    "first_name" => $this->user->first_name,
                    "middle_name" => $this->user->middle_name,
                    "last_name" => $this->user->last_name,
                    "email" => $this->user->email,
                ],
                "amount" => $amount,
                "wallet" => [
                    "old" => $old_wallet_balance,
                    "new" => $new_wallet_balance
                ]
            ])
            ->setUrl(SnitchConstants::parseUrl("safe-haven/webhook/verifications"))
            ->log();

        (new FcmPushNotificationService)
            ->setTitle("Receipt of fund")
            ->setBody("You have just received a payment of " . format_money($amount) . " from " . $transaction->sender_name)
            ->setType("Funding")
            ->setMetadata([
                "type" => "Funding",
                "transaction_id" => $transaction->id,
            ])
            ->byUserId($this->user->id)
            ->initiate();

        clearPendingDebits($wallet);

        if (!in_array($wallet->sub_type, globalSetting()->electronic_transfer_levy_exception)) {
            if ($amount >= globalSetting()->deposit_charge_req) {
                $this->clearServiceCharge($transaction);
            }
        }

        if ($fees > 0) {
            $wallet_id = SettingService::getKeyValue(sudo()->id, TransactionActivityConstants::DYNAMIC_ACCOUNT_FEE_INCOME);
            $fee_wallet = !empty($wallet_id) ? WalletService::getById($wallet_id) : null;
            (new SystemTransactionService($transaction->currency_id))
                ->setDescription("Dynamic Account Fees")
                ->setBatchNo($transaction->batch_no)
                ->setModelTypeAndId($fee_wallet->user)
                ->recorDynamicVirtualAccountFee($fees, $fee_wallet);
        }

        Log::info("If you can see this message and you do not see any newly created transaction for the user with userId " . $this->user->id . " and transaction reference " . $transaction->reference . " just know that the webhook reached that section of your code");
    }

    public function clearServiceCharge($transaction)
    {
        $wallet = $transaction->wallet;
        $amount = globalSetting()->deposit_service_charge;

        $old_wallet_balance = TransactionService::oldWalletBalance($wallet);
        $wallet = ApiWalletService::debit($wallet, $amount);
        $new_wallet_balance = TransactionService::newWalletBalance($wallet->refresh());

        $dep_transaction = TransactionService::create([
            "user_id" => $this->user->id,
            "currency_id" => $wallet->currency_id,
            "wallet_id" => $wallet->id,
            "amount" => $amount,
            "fees" => 0,
            "description" => "Electronic Transfer Levy",
            "activity" => TransactionActivityConstants::ELECTRONIC_TRANSFER_LEVY,
            "category" => TransactionConstants::ELECTRONIC_TRANSFER_LEVY,
            "type" => TransactionConstants::DEBIT,
            "source_provider" => PaymentConstants::GATEWAY_SAFE_HAVEN,
            "transaction_type" => TransactionConstants::CPAY_ACCOUNT_TRANSACTION,
            "action" => TransactionConstants::MONEY_SENT,
            "status" => StatusConstants::COMPLETED,
            "prev_wallet_balance" => $old_wallet_balance["balance"],
            "new_wallet_balance" => $new_wallet_balance["balance"],
            "batch_no" => $transaction->batch_no,
            "metadata" => json_encode([
                "wallet" => [
                    "old" => $old_wallet_balance,
                    "new" => $new_wallet_balance
                ]
            ])
        ]);

        if ($dep_transaction->amount > 0) {
            $wallet_id = SettingService::getKeyValue(sudo()->id, TransactionActivityConstants::ELECTRONIC_TRANSFER_LEVY);
            $levy_wallet = !empty($wallet_id) ? WalletService::getById($wallet_id) : null;
            (new SystemTransactionService($dep_transaction->currency_id))
                ->setDescription("Electronic Transfer Levy")
                ->setBatchNo($transaction->batch_no)
                ->setModelTypeAndId($levy_wallet->user ?? $dep_transaction->user)
                ->recordElectronicTransferLevy($dep_transaction->amount, $levy_wallet);
        }
    }

    public function parseMerchantFee($amount)
    {
        $final_fee = DepositLevelService::getDepositAmount($amount, $this->model);
        return $final_fee;
    }
}
