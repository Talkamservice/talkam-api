<?php

use App\Constants\General\StatusConstants;
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
<<<<<<<< HEAD:database/migrations/2024_08_04_014228_create_trending_searches_table.php
        Schema::create('trending_searches', function (Blueprint $table) {
========
        Schema::create('guidelines', function (Blueprint $table) {
>>>>>>>> 9d438a05bcf5dd64da5d585270a0980186b7ea93:database/migrations/2024_07_19_144551_create_guidelines_table.php
            $table->id();
            $table->string("word");
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->foreignId("category_id")->nullable()->constrained("post_categories")->cascadeOnDelete();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trending_searches');
    }
};
