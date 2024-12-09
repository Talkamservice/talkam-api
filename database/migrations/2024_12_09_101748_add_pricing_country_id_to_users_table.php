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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumns("users", ["pricing_country_id"])) {
                $table->foreignId('pricing_country_id')->nullable()->constrained("countries")->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumns("users", ["pricing_country_id"])) {
                $table->dropConstrainedForeignId('pricing_country_id');
            }
        });
    }
};
