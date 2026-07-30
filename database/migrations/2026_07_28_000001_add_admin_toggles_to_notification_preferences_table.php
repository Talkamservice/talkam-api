<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The B2B admin Settings screen's Notification Preferences card (web §03).
     * Personal to the admin's own account, like the rest of this table — not
     * org-scoped, so two admins on the same company can set these differently.
     */
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->tinyInteger('digest_summary')->nullable()->default(1);
            $table->tinyInteger('seat_limit_alerts')->nullable()->default(1);
            $table->tinyInteger('invoice_notifications')->nullable()->default(1);
            $table->tinyInteger('new_therapist_announcements')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'digest_summary',
                'seat_limit_alerts',
                'invoice_notifications',
                'new_therapist_announcements',
            ]);
        });
    }
};
