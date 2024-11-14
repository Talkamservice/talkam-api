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
        Schema::create('promotion_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId("promotion_id")->constrained("promotions")->cascadeOnDelete(); 
            $table->foreignId("country_id")->constrained("countries")->cascadeOnDelete();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_locations');
    }
};
