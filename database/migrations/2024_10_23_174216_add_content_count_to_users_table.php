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
            if (!Schema::hasColumns("users", ["anonymous_post", "anonymous_comment"])) {
                $table->integer('anonymous_post')->nullable()->default(0);
                $table->integer('anonymous_comment')->nullable()->default(0);
                $table->integer('public_group_count')->nullable()->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumns("users", ["anonymous_post", "anonymous_comment"])) {
                $table->dropColumn('anonymous_post');
                $table->dropColumn('anonymous_comment');
            }
        });
    }
};
