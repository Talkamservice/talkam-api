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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId("admin_id")->constrained("users")->cascadeOnDelete();
            $table->string("title");
            $table->string("channel")->nullable();
            $table->string("model")->nullable();
            $table->foreignId("model_id")->nullable();
            $table->string("external_model")->nullable();
            $table->foreignId("external_model_id")->nullable();
            $table->string("source");
            $table->string("event");
            $table->string("type")->nullable();
            $table->text("url")->nullable();
            $table->text("tags")->nullable();
            $table->text("description")->nullable();
            $table->string("activity")->nullable();
            $table->longText("current_data")->nullable();
            $table->longText("previous_data")->nullable();
            $table->longText("metadata")->nullable();
            $table->string("trigger")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
