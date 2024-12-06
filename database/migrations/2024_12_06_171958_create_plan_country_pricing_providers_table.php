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
        Schema::create('plan_country_pricing_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId("plan_country_pricing_id")->constrained("plan_country_pricings")->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->cascadeOnDelete();
            $table->foreignId('plan_duration_id')->nullable()->constrained('plan_durations')->nullOnDelete();
            $table->double('price' , 12 , 2)->nullable();
            $table->double("discount")->nullable();
            $table->string("provider")->nullable();
            $table->string("provider_plan_id")->nullable();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });

        Schema::table('plan_country_pricings', function (Blueprint $table) {
            if (Schema::hasColumns("plan_country_pricings", ["flutterwave_plan_id"])) {
                $table->dropColumn("flutterwave_plan_id");
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_country_pricing_providers');
    }
};
