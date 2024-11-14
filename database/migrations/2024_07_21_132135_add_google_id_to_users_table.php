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
            if (!Schema::hasColumns("users", ["google_id"])) {
                $table->string('google_id')->nullable()->after("social_id");
                $table->string('facebook_id')->nullable()->after("google_id");
                $table->string('apple_user_id')->nullable()->after("facebook_id");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumns("users", ["google_id"])) {
                $table->dropColumn('google_id');
                $table->dropColumn('facebook_id');
                $table->dropColumn('apple_user_id');
            }
        });
    }
};
