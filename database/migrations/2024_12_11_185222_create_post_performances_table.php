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
        Schema::create('post_performances', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('avg_impressions_per_day')->default(0);
            $table->bigInteger('avg_time_spent_per_day')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_performances');
    }
};
