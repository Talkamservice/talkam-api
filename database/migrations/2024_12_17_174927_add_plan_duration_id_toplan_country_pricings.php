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
        Schema::table('plan_country_pricings', function (Blueprint $table) {
            if (!Schema::hasColumns("plan_country_pricings", ["plan_duration_id"])) {
                $table->foreignId('plan_duration_id')->nullable()->after("country_id")->constrained("plan_durations");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_country_pricings', function (Blueprint $table) {
            if (Schema::hasColumns("plan_country_pricings", ["plan_duration_id"])) {
                $table->dropConstrainedForeignId('plan_duration_id');
            }
        });
    }
};
