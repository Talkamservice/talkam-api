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
        Schema::table('post_stats', function (Blueprint $table) {
            if (!Schema::hasColumns("post_stats", ["group_id"])) {
                $table->foreignId("group_id")->nullable()->after("post_id")->constrained("groups")->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_stats', function (Blueprint $table) {
            if (Schema::hasColumns("post_stats", ["group_id"])) {
                $table->dropConstrainedForeignId("group_id");
            }
        });
    }
};
