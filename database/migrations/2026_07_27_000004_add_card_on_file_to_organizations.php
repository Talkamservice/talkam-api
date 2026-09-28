<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card-on-file for postpay orgs (web §10). ADDITIVE.
 *
 * Stores the Flutterwave card TOKEN (a reusable handle, not raw card data)
 * captured at onboarding, plus the last4/brand for display. Reused by the
 * month-end auto-charge via FlutterwaveService::chargeWithToken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->string("card_token")->nullable()->after("pay_method");
            $table->string("card_last4", 4)->nullable()->after("card_token");
            $table->string("card_brand", 32)->nullable()->after("card_last4");
            $table->timestamp("card_setup_at")->nullable()->after("card_brand");
        });
    }

    public function down(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->dropColumn(["card_token", "card_last4", "card_brand", "card_setup_at"]);
        });
    }
};
