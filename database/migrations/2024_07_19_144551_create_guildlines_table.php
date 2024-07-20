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
        Schema::create('guildlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId("group_id")->nullable()->constrained("groups")->cascadeOnUpdate()->cascadeOnDelete();
            $table->string("title");
            $table->longText("description")->nullable();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guildlines');
    }
};
