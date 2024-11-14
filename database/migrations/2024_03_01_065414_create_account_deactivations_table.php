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
        Schema::create('account_deactivations', function (Blueprint $table) {
            $table->id();
            $table->string("email");
            $table->longText("reason")->nullable();
            $table->foreignId("user_id")->nullable()->constrained("users")->nullOnDelete();
            $table->string("status");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_deactivations');
    }
};
