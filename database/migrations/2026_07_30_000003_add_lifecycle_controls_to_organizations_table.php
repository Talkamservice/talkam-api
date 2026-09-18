<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Settings → Danger Zone (web §03). ADDITIVE + INERT until the admin
 * actions that set them ship.
 *
 *  - employees_suspended_at: temporary, admin-reversible — blocks the
 *    employee role only, everywhere (business dashboard + the app itself).
 *    The admin who set it is never locked out.
 *  - cancels_at: the subscription's scheduled end (current billing period's
 *    close). A sweep flips `status` to "cancelled" once it passes, which the
 *    existing STATUS_ACTIVE-only queries (billing run, org access checks)
 *    already treat as inactive.
 *  - scheduled_deletion_at: the 30-day grace-period clock for company
 *    deletion; a sweep purges once it passes. Reversible until then.
 *  - deleted_at: soft-deletes, so a purged organization is excluded from
 *    normal queries without destroying the row outright.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->timestamp("employees_suspended_at")->nullable()->after("status");
            $table->timestamp("cancels_at")->nullable()->after("employees_suspended_at");
            $table->timestamp("scheduled_deletion_at")->nullable()->after("cancels_at");
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table("organizations", function (Blueprint $table) {
            $table->dropColumn(["employees_suspended_at", "cancels_at", "scheduled_deletion_at"]);
            $table->dropSoftDeletes();
        });
    }
};
