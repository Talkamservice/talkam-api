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
        Schema::create('user_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained("users")->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained("post_comments")->cascadeOnDelete();
            $table->string('action')->nullable(); // Like, Dislike
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_comment_reactions');
    }
};
