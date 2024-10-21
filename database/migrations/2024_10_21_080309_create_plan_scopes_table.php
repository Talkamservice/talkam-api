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
        Schema::create('plan_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId("plan_id")->constrained("plans")->cascadeOnDelete();
            $table->string("title")->nullable();
            $table->string("slug")->unique();
            $table->string("value")->nullable();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_scopes');
    }
};
