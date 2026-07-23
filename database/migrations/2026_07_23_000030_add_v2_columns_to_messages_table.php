<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * All nullable/defaulted — v1 queries never touch them.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->longText('original_message')->nullable();
            $table->foreignId('replied_to_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->integer('reply_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_forwarded')->default(false);
            $table->foreignId('forwarded_from_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('delete_type')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->integer('voice_duration')->nullable();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['conversation_id', 'message_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_to_message_id');
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropConstrainedForeignId('forwarded_from_message_id');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropConstrainedForeignId('file_id');
            $table->dropColumn([
                'delivered_at', 'read_at', 'edited_at', 'original_message',
                'reply_count', 'is_pinned', 'pinned_at', 'is_forwarded',
                'delete_type', 'voice_duration',
            ]);
        });
    }
};
