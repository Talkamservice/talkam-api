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
        Schema::table('currencies', function (Blueprint $table) {
            if (!Schema::hasColumns("currencies", ["price_per_dollar"])) {
                $table->double('price_per_dollar')->nullable()->after("symbol");
            }
        });

        Schema::table('currencies', function (Blueprint $table) {
            if (Schema::hasColumns("currencies", ["symbol"])) {
                $table->string('symbol')->nullable()->change();
                $table->string('type')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (Schema::hasColumns("currencies", ["price_per_dollar"])) {
                $table->dropColumn('price_per_dollar');
            }
        });
    }
};
