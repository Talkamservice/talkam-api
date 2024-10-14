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
        Schema::table('post_categories', function (Blueprint $table) {
            if (!Schema::hasColumns("post_categories", ["uuid"])) {
                $table->string('uuid')->nullable()->unique()->after("description");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_categories', function (Blueprint $table) {
            if (Schema::hasColumns("post_categories", ["uuid"])) {
                $table->dropColumn('uuid');
            }
        });
    }
};
