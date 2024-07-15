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
        Schema::create('group_executives', function (Blueprint $table) {
            $table->id();
            $table->foreignId("group_id")->constrained("groups")->cascadeOnDelete();
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->string("role")->nullable();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_executives');
    }
};
