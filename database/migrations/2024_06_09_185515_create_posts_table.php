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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('body')->nullable();
            $table->string('type')->nullable(); // Text, File or Poll
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained("users")->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained("post_categories")->cascadeOnDelete();
            $table->string('cover')->nullable();
            $table->tinyInteger('can_comment')->default("1");
            $table->tinyInteger('is_anonymous')->default("0");
            $table->integer('views_count')->default(0);
            $table->string('status');
            $table->dateTime("publish_at")->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
