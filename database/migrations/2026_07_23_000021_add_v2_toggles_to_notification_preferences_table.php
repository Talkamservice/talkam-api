<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * v2 session/community toggle columns — v1's service never reads them.
     */
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->tinyInteger('session_confirmation')->nullable()->default(1);
            $table->tinyInteger('session_reminders')->nullable()->default(1);
            $table->tinyInteger('post_session_feedback')->nullable()->default(1);
            $table->tinyInteger('payment_confirmations')->nullable()->default(1);
            $table->tinyInteger('replies_to_posts')->nullable()->default(1);
            $table->tinyInteger('promotions_updates')->nullable()->default(1);
            $table->tinyInteger('wellness_nudges')->nullable()->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'session_confirmation',
                'session_reminders',
                'post_session_feedback',
                'payment_confirmations',
                'replies_to_posts',
                'promotions_updates',
                'wellness_nudges',
            ]);
        });
    }
};
