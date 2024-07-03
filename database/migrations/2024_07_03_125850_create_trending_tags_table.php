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
        Schema::create('trending_tags', function (Blueprint $table) {
            $table->id();
            $table->string("tag");
            $table->foreignId("category_id")->nullable()->constrained("post_categories")->cascadeOnDelete();
            $table->bigInteger("count")->default(0);
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trending_tags');
    }
};
