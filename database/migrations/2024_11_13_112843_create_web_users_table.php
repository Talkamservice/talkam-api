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
        Schema::create('web_users', function (Blueprint $table) {
            $table->id();
            $table->string("identifier")->unique();
            $table->foreignId("country_id")->nullable()->constrained("countries")->cascadeOnDelete();
            $table->foreignId("state_id")->nullable()->constrained("states")->cascadeOnDelete();
            $table->string('status')->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_users');
    }
};
