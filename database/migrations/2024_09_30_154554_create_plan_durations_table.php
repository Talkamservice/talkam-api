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
        Schema::create('plan_durations', function (Blueprint $table) {
            $table->id();
            $table->foreignId("plan_id")->constrained("plans")->cascadeOnDelete();
            $table->string("frequency")->nullable();
            $table->string("duration");
            $table->double('price' , 12 , 2);
            $table->double("discount")->nullable();
            $table->tinyInteger('is_default')->default(0);
            $table->string("status")->nullable()->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_durations');
    }
};
