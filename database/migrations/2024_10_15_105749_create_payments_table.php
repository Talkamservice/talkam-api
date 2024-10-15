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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->string("currency")->nullable();
            $table->double("amount");
            $table->double("fees")->default(0);
            $table->string("reference")->unique();
            $table->string("activity");
            $table->string("description")->nullable();
            $table->string("narration")->nullable();
            $table->string("type");
            $table->longText("metadata")->nullable();
            $table->string("status")->default(StatusConstants::PENDING);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
