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
        Schema::table('plan_durations', function (Blueprint $table) {
            if (!Schema::hasColumns("plan_durations", ["flutterwave_plan_id"])) {
                $table->string('flutterwave_plan_id')->nullable()->after("is_default");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_durations', function (Blueprint $table) {
            if (Schema::hasColumns("plan_durations", ["flutterwave_plan_id"])) {
                $table->dropColumn('flutterwave_plan_id');
            }
        });
    }
};
