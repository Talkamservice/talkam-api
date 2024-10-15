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
        Schema::create('plan_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId("plan_id")->constrained("plans")->cascadeOnDelete();
            $table->string('title');
            $table->string('key');
            $table->string('value')->nullable();
            $table->text('description')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_benefits');
    }
};
