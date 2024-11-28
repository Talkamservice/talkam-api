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
        Schema::create('plan_country_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id', 2)->constrained('countries')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->nullable()->after('id');
            $table->double('lowered_cost', 12, 2)->nullable();    // Country-specific price
            $table->string('status')->nullable()->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_country_pricings');
    }
};
