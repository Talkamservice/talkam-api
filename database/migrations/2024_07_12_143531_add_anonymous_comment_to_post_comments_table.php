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
        Schema::table('post_comments', function (Blueprint $table) {
            if (!Schema::hasColumn("post_comments", "is_anonymous")) {
                $table->tinyInteger('is_anonymous')->nullable()->default("0");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            if (Schema::hasColumn("post_comments", "is_anonymous")) {
                $table->dropColumn('is_anonymous');
            }
        });
    }
};
