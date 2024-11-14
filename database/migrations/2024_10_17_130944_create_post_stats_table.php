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
        Schema::create('post_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId("post_id")->nullable()->constrained("posts")->cascadeOnDelete();
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->bigInteger("comments")->default(0);
            $table->bigInteger("likes")->default(0);
            $table->bigInteger("dislikes")->default(0);
            $table->bigInteger("shares")->default(0);
            $table->bigInteger("impressions")->default(0);
            $table->bigInteger("engagements")->default(0);
            $table->bigInteger("followers")->default(0);
            $table->bigInteger("profile_visits")->default(0);
            $table->bigInteger("clicks")->default(0);
            $table->bigInteger("min_time_spent")->default(0); // Seconds
            $table->bigInteger("max_time_spent")->default(0); // Seconds
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_stats');
    }
};
