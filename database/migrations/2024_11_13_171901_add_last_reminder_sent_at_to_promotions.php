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
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumns("promotions", ["last_reminder_sent_at"])) {
            $table->timestamp('last_reminder_sent_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
                if (Schema::hasColumns("promotions", ["last_reminder_sent_at"])) {
                    $table->dropColumn("last_reminder_sent_at");
                }
        });
    }
};
