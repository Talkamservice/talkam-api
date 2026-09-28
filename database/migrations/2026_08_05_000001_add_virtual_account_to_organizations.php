<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated NGN virtual accounts for bank-transfer orgs (web §11). ADDITIVE.
 *
 * Each bank-transfer org gets its own permanent Flutterwave account so transfers
 * auto-reconcile via webhook. We store only the account handles and the MINIMUM
 * KYC footprint — the raw BVN/NIN is passed to Flutterwave and never persisted
 * here (only the id TYPE, its last 4, and the consent timestamp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            // Flutterwave account handles.
            $table->string("va_account_number", 32)->nullable()->after("card_setup_at");
            $table->string("va_bank_name")->nullable()->after("va_account_number");
            $table->string("va_reference")->nullable()->after("va_bank_name");   // FLW order_ref / flw_ref
            $table->string("va_tx_ref")->nullable()->after("va_reference");      // our stable ref
            $table->string("va_status", 16)->nullable()->after("va_tx_ref");
            $table->timestamp("va_created_at")->nullable()->after("va_status");

            // Minimal KYC footprint — NO raw BVN/NIN column by design.
            $table->string("kyc_id_type", 8)->nullable()->after("va_created_at"); // bvn | nin
            $table->string("kyc_id_last4", 4)->nullable()->after("kyc_id_type");
            $table->timestamp("kyc_consent_at")->nullable()->after("kyc_id_last4");

            // Overpayment carried forward and drawn against future invoices.
            $table->decimal("credit_balance", 12, 2)->default(0)->after("kyc_consent_at");

            $table->index("va_account_number");
        });
    }

    public function down(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->dropIndex(["va_account_number"]);
            $table->dropColumn([
                "va_account_number", "va_bank_name", "va_reference", "va_tx_ref",
                "va_status", "va_created_at", "kyc_id_type", "kyc_id_last4",
                "kyc_consent_at", "credit_balance",
            ]);
        });
    }
};
