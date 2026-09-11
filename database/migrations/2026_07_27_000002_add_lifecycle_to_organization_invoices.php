<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice lifecycle timestamps for the monthly billing run (web §08 Phase 2a).
 * ADDITIVE ONLY.
 *
 * - paid_at: when a net-terms invoice was reconciled as settled (offline bank
 *   transfer, marked by an admin).
 * - reminded_at / overdue_notified_at: idempotency guards so the daily sweep
 *   sends the "due soon" reminder and the "overdue" notice at most once each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("organization_invoices", function (Blueprint $table) {
            $table->timestamp("paid_at")->nullable()->after("due_at");
            $table->timestamp("reminded_at")->nullable()->after("paid_at");
            $table->timestamp("overdue_notified_at")->nullable()->after("reminded_at");
        });
    }

    public function down(): void
    {
        Schema::table("organization_invoices", function (Blueprint $table) {
            $table->dropColumn(["paid_at", "reminded_at", "overdue_notified_at"]);
        });
    }
};
