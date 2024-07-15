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
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId("group_id")->constrained("groups")->cascadeOnDelete();
            $table->foreignId("user_id")->nullable()->constrained("users")->cascadeOnDelete();
            $table->string("role")->nullable();
            $table->string("status")->default(StatusConstants::ACTIVE);
            $table->timestamps();
        });

        Schema::table('recent_views', function (Blueprint $table) {
            if (!Schema::hasColumn("recent_views", "group_id")) {
                $table->foreignId('group_id')->nullable()->constrained("groups")->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_members');

        Schema::table('recent_views', function (Blueprint $table) {
            if (Schema::hasColumn("recent_views", "group_id")) {
                $table->dropConstrainedForeignId('group_id');
            }
        });
    }
};
