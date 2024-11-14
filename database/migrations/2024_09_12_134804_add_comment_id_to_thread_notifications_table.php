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
        Schema::table('thread_notifications', function (Blueprint $table) {
            if (!Schema::hasColumns("thread_notifications", ["comment_id"])) {
                $table->foreignId("comment_id")->nullable()->after("post_id")->constrained("post_comments");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thread_notifications', function (Blueprint $table) {
            if (Schema::hasColumns("thread_notifications", ["comment_id"])) {
                $table->dropConstrainedForeignId("comment_id");
            }
        });
    }
};
