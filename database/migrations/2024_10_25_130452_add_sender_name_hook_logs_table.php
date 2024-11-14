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
        Schema::table('hook_logs', function (Blueprint $table) {
            if (!Schema::hasColumns("hook_logs", ["sender_name"])) {
                $table->string("sender_name")->nullable()->after("user_id");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hook_logs', function (Blueprint $table) {
            if (Schema::hasColumns("hook_logs", ["sender_name"])) {
                $table->dropColumn("sender_name");
            }
        });
    }
};
