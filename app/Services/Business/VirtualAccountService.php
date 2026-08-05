<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants as OC;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\MethodsHelper;
use App\Models\Organization;
use App\Models\OrganizationInvoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Support\Facades\DB;

/**
 * Dedicated NGN virtual accounts (web §11).
 *
 * Each bank-transfer org gets its OWN permanent Flutterwave account, so a transfer
 * fires a webhook we can match to the org and auto-apply to its open invoices —
 * self-reconciling, no customer self-marking-paid.
 *
 * PRIVACY: a static NGN account needs the customer's BVN or NIN. That raw value is
 * sent to Flutterwave ONLY and never persisted here — we keep just the id type, its
 * last 4, and the consent timestamp (web §11 decision 4).
 */
class VirtualAccountService
{
    /**
     * Create (once) the org's dedicated account from a director's BVN/NIN. Gated by
     * business.virtual_accounts_enabled. Idempotent — an org that already has an
     * account keeps it. The raw id is passed to Flutterwave and never stored.
     */
    public static function create(Organization $organization, User $admin, string $idType, string $idNumber): Organization
    {
        if (!config("business.virtual_accounts_enabled")) {
            throw new InvalidRequestException("Bank-transfer account setup isn't available yet.");
        }

        $idType = strtolower(trim($idType));
        if (!in_array($idType, config("business.kyc_id_types"), true)) {
            throw new InvalidRequestException("Provide a valid BVN or NIN.");
        }

        $idNumber = preg_replace("/\D/", "", (string) $idNumber);
        if (strlen($idNumber) !== 11) {
            throw new InvalidRequestException("Your " . strtoupper($idType) . " must be 11 digits.");
        }

        // Idempotent: never create a second account for the same org.
        if (!empty($organization->va_account_number)) {
            return $organization;
        }

        $tx_ref = "TK-VA-{$organization->id}-" . strtoupper(MethodsHelper::getRandomToken(8));

        $account = app(FlutterwaveService::class)->createVirtualAccount([
            "email" => $admin->email,
            "is_permanent" => true,
            "tx_ref" => $tx_ref,
            "narration" => $organization->name,
            // bvn OR nin — sent to Flutterwave only, NEVER persisted by TalkAM.
            $idType => $idNumber,
        ]);

        return self::storeAccount($organization, $account, $idType, substr($idNumber, -4), $tx_ref);
    }

    /**
     * Persist the Flutterwave account handles + the minimal KYC footprint. Public
     * so it is unit-testable without a live gateway call (mirrors saveCardOnFile).
     * NEVER writes the raw BVN/NIN.
     */
    public static function storeAccount(
        Organization $organization,
        array $account,
        string $idType,
        string $idLast4,
        ?string $tx_ref = null
    ): Organization {
        $number = $account["account_number"] ?? null;

        if (empty($number)) {
            throw new InvalidRequestException("The virtual account could not be created.");
        }

        $organization->update([
            "va_account_number" => $number,
            "va_bank_name" => $account["bank_name"] ?? "Flutterwave MFB",
            "va_reference" => $account["order_ref"] ?? $account["flw_ref"] ?? null,
            "va_tx_ref" => $tx_ref ?? ($account["tx_ref"] ?? null),
            "va_status" => "active",
            "va_created_at" => now(),
            "kyc_id_type" => $idType,
            "kyc_id_last4" => $idLast4,
            "kyc_consent_at" => now(),
        ]);

        return $organization->refresh();
    }

    /**
     * Match an incoming Flutterwave bank-transfer to the org that owns the account.
     * The exact identifying field isn't certain from docs (web §11 risk note), so we
     * match on ANY of account number / our tx_ref / the stored reference.
     */
    public static function resolveForTransfer(array $data): ?Organization
    {
        $number = $data["account_number"] ?? ($data["account"]["account_number"] ?? null);
        $tx_ref = $data["tx_ref"] ?? null;
        $reference = $data["flw_ref"] ?? ($data["order_ref"] ?? null);

        if (empty($number) && empty($tx_ref) && empty($reference)) {
            return null;
        }

        return Organization::query()
            ->whereNotNull("va_account_number")
            ->where(function ($q) use ($number, $tx_ref, $reference) {
                if ($number) {
                    $q->orWhere("va_account_number", $number);
                }
                if ($tx_ref) {
                    $q->orWhere("va_tx_ref", $tx_ref);
                }
                if ($reference) {
                    $q->orWhere("va_reference", $reference);
                }
            })
            ->first();
    }

    /**
     * Record a received transfer and apply it. Idempotent on the Flutterwave
     * reference — a webhook retry never double-applies. Audited as a CREDIT Payment.
     */
    public static function recordTransfer(Organization $organization, float $amount, string $reference, ?User $payer = null): array
    {
        return DB::transaction(function () use ($organization, $amount, $reference, $payer) {
            $existing = Payment::where("reference", $reference)->first();
            if ($existing && $existing->status === StatusConstants::COMPLETED) {
                return ["applied" => false, "reason" => "duplicate", "paid" => []];
            }

            if (empty($existing)) {
                Payment::create([
                    "user_id" => $payer?->id ?? self::firstAdminId($organization),
                    "currency" => config("business.currency"),
                    "amount" => $amount,
                    "reference" => $reference,
                    "activity" => PaymentConstants::PAYMENT_FOR_BUSINESS_TRANSFER,
                    "description" => "Bank transfer received — {$organization->name}",
                    "type" => PaymentConstants::CREDIT,
                    "metadata" => ["organization_id" => $organization->id],
                    "status" => StatusConstants::COMPLETED,
                ]);
            }

            $result = self::applyToInvoices($organization, $amount);
            $result["applied"] = true;

            return $result;
        });
    }

    /**
     * Clear open invoices oldest-due first, drawing on any prior credit plus this
     * amount. Fully-covered invoices are marked paid (which releases held therapist
     * credits); the remainder is carried as credit for the next transfer/invoice.
     * Strict FIFO — stops at the first invoice it cannot fully cover.
     */
    public static function applyToInvoices(Organization $organization, float $amount): array
    {
        $available = round((float) $organization->credit_balance + (float) $amount, 2);
        $paid = [];

        $due = OrganizationInvoice::where("organization_id", $organization->id)
            ->where("status", OrganizationInvoice::STATUS_DUE)
            ->orderBy("issued_at")
            ->orderBy("period_start")
            ->get();

        foreach ($due as $invoice) {
            $owed = round((float) $invoice->amount, 2);

            if ($owed <= 0) {
                continue;
            }

            if ($available + 0.001 < $owed) {
                break; // FIFO: don't skip an older invoice to pay a newer one.
            }

            OrganizationBillingRunService::markPaid($invoice);
            $available = round($available - $owed, 2);
            $paid[] = $invoice->reference;
        }

        $organization->update(["credit_balance" => $available]);

        return ["paid" => $paid, "credit_balance" => $available];
    }

    /** Public view of the org's account for the billing screen (no KYC secrets). */
    public static function publicView(Organization $organization): ?array
    {
        if (empty($organization->va_account_number)) {
            return null;
        }

        return [
            "account_number" => $organization->va_account_number,
            "bank_name" => $organization->va_bank_name,
            "id_type" => $organization->kyc_id_type,
            "id_last4" => $organization->kyc_id_last4,
            "verified_at" => optional($organization->kyc_consent_at)->toDateString(),
            "credit_balance" => (float) $organization->credit_balance,
        ];
    }

    private static function firstAdminId(Organization $organization): ?int
    {
        $admin = $organization->members()
            ->where("role", OC::ROLE_ADMIN)
            ->where("status", OC::MEMBER_ACTIVE)
            ->first();

        return $admin?->user_id ?? $organization->created_by;
    }
}
