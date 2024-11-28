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
            if (!Schema::hasColumns("plan_country_pricings", ["percentage"])) {
                $table->string('percentage')->nullable()->before('discount');
                }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_country_pricings', function (Blueprint $table) {
            if (Schema::hasColumns("plan_country_pricings", ["percentage"])) {
                $table->dropColumn("percentage");
            }
        });
    }
};
