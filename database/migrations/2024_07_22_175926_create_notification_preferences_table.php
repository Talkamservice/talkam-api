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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->tinyInteger("talkam_news")->nullable()->default(0);
            $table->tinyInteger("talkam_research")->nullable()->default(0);
            $table->tinyInteger("moderation_activities")->nullable()->default(0);
            $table->tinyInteger("user_activities")->nullable()->default(0);
            $table->string("comments")->nullable(); // mentions, all
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
