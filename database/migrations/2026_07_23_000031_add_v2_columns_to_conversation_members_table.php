<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-member conversation state (mute/archive/star/seen/typing) —
     * per-member because a shared conversation status is wrong for two
     * people (Fundnai's mistake).
     */
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('last_seen_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->boolean('is_typing')->default(false);
            $table->timestamp('last_typing_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_at')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('starred_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_seen_message_id');
            $table->dropColumn([
                'last_seen_at', 'is_typing', 'last_typing_at',
                'is_muted', 'muted_at', 'muted_until', 'archived_at', 'starred_at',
            ]);
        });
    }
};
