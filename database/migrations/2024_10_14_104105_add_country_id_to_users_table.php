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
            if (!Schema::hasColumns("users", ["country_id", "state_id"])) {
                $table->foreignId('state_id')->nullable()->constrained("states");
                $table->foreignId('country_id')->nullable()->constrained("countries");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumns("users", ["country_id", "state_id"])) {
                $table->dropConstrainedForeignId('state_id');
                $table->dropConstrainedForeignId('country_id');
            }
        });
    }
};
