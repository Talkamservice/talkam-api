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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignId("post_id")->nullable()->constrained("posts")->nullOnDelete();
            $table->foreignId("group_id")->nullable()->constrained("groups")->nullOnDelete();
            $table->foreignId("state_id")->nullable()->constrained("states")->nullOnDelete();
            $table->foreignId("country_id")->nullable()->constrained("countries")->nullOnDelete();
            $table->string("uuid")->unique();
            $table->integer("min_age")->nullable();
            $table->integer("max_age")->nullable();
            $table->integer("gender")->nullable();
            $table->bigInteger("daily_budget")->nullable();
            $table->bigInteger("duration")->nullable();
            $table->bigInteger("estimated_reach")->nullable();
            $table->bigInteger("total_reach")->nullable();
            $table->string("status")->default(StatusConstants::PENDING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
