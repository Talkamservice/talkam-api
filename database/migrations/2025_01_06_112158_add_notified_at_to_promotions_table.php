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
            if (!Schema::hasColumns("promotions", ["notified_at"])) {
                $table->timestamp('notified_at')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumns("promotions", ["notified_at"])) {
                $table->dropConstrainedForeignId('notified_at');
            }
        });
    }
};
