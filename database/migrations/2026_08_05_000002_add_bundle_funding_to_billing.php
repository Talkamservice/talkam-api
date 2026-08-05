<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepay activates on payment (web §08/§11). ADDITIVE.
 *
 * A prepaid bundle is usable only once it's actually paid — by card at signup, or
 * when the bank transfer into the dedicated account reconciles. `session_bundle_funded_at`
 * records that moment; `organization_invoices.bundle_sessions` marks a first
 * invoice that carries a bundle, so paying it flips the bundle live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->timestamp("session_bundle_funded_at")->nullable()->after("credit_balance");
        });

        Schema::table("organization_invoices", function (Blueprint $table) {
            $table->unsignedInteger("bundle_sessions")->default(0)->after("seats");
        });
    }

    public function down(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->dropColumn("session_bundle_funded_at");
        });

        Schema::table("organization_invoices", function (Blueprint $table) {
            $table->dropColumn("bundle_sessions");
        });
    }
};
