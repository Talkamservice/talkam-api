<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing redesign (web §08). ADDITIVE ONLY.
 *
 * - payment_timing: 'prepay' (buy a session bundle upfront) or 'postpay'
 *   (pay-as-you-go, metered at month-end). Defaults to 'prepay' so existing
 *   rows keep the current behaviour.
 * - bundle_custom: whether the pre-purchased bundle used a custom (non-block)
 *   quantity, which prices each session at the +3% custom rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->string("payment_timing", 10)->default("prepay")->after("pay_method");
            $table->boolean("bundle_custom")->default(false)->after("session_bundle_sessions");
        });
    }

    public function down(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->dropColumn(["payment_timing", "bundle_custom"]);
        });
    }
};
