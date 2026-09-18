<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One-shot guard for the seat-limit alert (web §03 Settings → "Seat limit
     * alerts"): set when an org drops to/below the threshold so the daily sweep
     * doesn't re-send every day; cleared once seats free back up above it, so a
     * future dip alerts again.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->timestamp('seat_alert_sent_at')->nullable()->after('session_bundle_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('seat_alert_sent_at');
        });
    }
};
