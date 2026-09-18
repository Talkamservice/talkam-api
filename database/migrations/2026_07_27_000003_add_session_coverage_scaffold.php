<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B session-coverage scaffold (web §09 Phase 9.1a). ADDITIVE + INERT.
 *
 * These columns/table are written by the coverage layer once the booking branch
 * is wired (9.1b, feature-flagged). Adding them changes NO live behaviour —
 * `coverage` defaults to "consumer", so every existing and new session stays a
 * consumer session until the flip. `session_bundle_sessions` remains the
 * purchased total; `session_bundle_used` is the drawdown counter
 * (remaining = purchased − used); the entries table is the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("therapy_sessions", function (Blueprint $table) {
            $table->foreignId("organization_id")->nullable()->after("user_id")->constrained()->nullOnDelete();
            $table->string("coverage", 20)->default("consumer")->after("status"); // consumer|org_bundle|org_meter|org_external
            $table->decimal("billed_amount", 12, 2)->default(0)->after("amount"); // what the ORG is charged (0 for consumer/external)
            $table->foreignId("organization_invoice_id")->nullable()->after("payment_id")->constrained("organization_invoices")->nullOnDelete();
        });

        Schema::table("organizations", function (Blueprint $table) {
            $table->unsignedInteger("session_bundle_used")->default(0)->after("session_bundle_sessions");
            $table->string("bundle_exhausted_policy", 20)->default("force_top_up")->after("session_bundle_used");
            $table->unsignedInteger("per_employee_session_quota")->nullable()->after("bundle_exhausted_policy");
        });

        Schema::create("organization_bundle_entries", function (Blueprint $table) {
            $table->id();
            $table->foreignId("organization_id")->constrained()->cascadeOnDelete();
            $table->foreignId("therapy_session_id")->nullable()->constrained()->nullOnDelete();
            $table->integer("delta"); // +N purchase, -1 draw, +1 refund
            $table->string("reason", 20); // purchase|draw|refund|adjust
            $table->timestamps();

            $table->index(["organization_id", "reason"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("organization_bundle_entries");

        Schema::table("organizations", function (Blueprint $table) {
            $table->dropColumn(["session_bundle_used", "bundle_exhausted_policy", "per_employee_session_quota"]);
        });

        Schema::table("therapy_sessions", function (Blueprint $table) {
            $table->dropConstrainedForeignId("organization_id");
            $table->dropConstrainedForeignId("organization_invoice_id");
            $table->dropColumn(["coverage", "billed_amount"]);
        });
    }
};
