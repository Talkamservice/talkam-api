<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            // Idempotency guard for the one-time "welcome to your dashboard"
            // email — TherapistDashboardService::home() has no other flag to
            // detect a first visit, and runs on every load with no side
            // effects otherwise.
            $table->timestamp('welcome_email_sent_at')->nullable()->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->dropColumn('welcome_email_sent_at');
        });
    }
};
