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
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumns("subscriptions", ["renewal_cancelled_at"])) {
                $table->dateTime("renewal_cancelled_at")->nullable()->after("status");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumns("subscriptions", ["renewal_cancelled_at"])) {
                $table->dropColumn("renewal_cancelled_at");
            }
        });
    }
};
