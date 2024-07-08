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
            if (!Schema::hasColumn("post_comments", "reply_comment_id")) {
                $table->foreignId('reply_comment_id')->nullable()->after("parent_id")->constrained("post_comments")->cascadeOnDelete();
            }
        });

        Schema::table('post_polls', function (Blueprint $table) {
            if (!Schema::hasColumn("post_polls", "duration")) {
                $table->bigInteger("duration")->nullable()->after("type"); // In minutes
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            if (Schema::hasColumn("post_comments", "reply_comment_id")) {
                $table->dropConstrainedForeignId('reply_comment_id');
            }
        });

        Schema::table('post_polls', function (Blueprint $table) {
            if (Schema::hasColumn("post_polls", "duration")) {
                $table->dropColumn("duration");
            }
        });
    }
};
