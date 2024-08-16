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
<<<<<<< HEAD
        if (!Schema::hasTable("guidelines")) {
            Schema::create('guidelines', function (Blueprint $table) {
                $table->id();
                $table->foreignId("group_id")->nullable()->constrained("groups")->cascadeOnUpdate()->cascadeOnDelete();
                $table->string("title");
                $table->longText("description")->nullable();
                $table->string("status")->default(StatusConstants::ACTIVE);
                $table->timestamps();
            });
        }
=======
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
>>>>>>> 9d438a05bcf5dd64da5d585270a0980186b7ea93
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
<<<<<<< HEAD
        Schema::dropIfExists('guildlines');
=======
        Schema::dropIfExists('trending_searches');
>>>>>>> 9d438a05bcf5dd64da5d585270a0980186b7ea93
    }
};
