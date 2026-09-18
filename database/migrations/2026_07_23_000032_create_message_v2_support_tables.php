<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * message_edits (history), message_reactions (unique triple),
     * message_drafts (unique pair), message_hides (delete-for-me).
     */
    public function up(): void
    {
        Schema::create('message_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->longText('original_content')->nullable();
            $table->longText('new_content')->nullable();
            $table->foreignId('edited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reaction', 32);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'reaction']);
        });

        Schema::create('message_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->longText('content')->nullable();
            $table->foreignId('replied_to_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'conversation_id']);
        });

        Schema::create('message_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_hides');
        Schema::dropIfExists('message_drafts');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_edits');
    }
};
