<?php

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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn("users", "strike")) {
                $table->integer('strike')->nullable()->after("status")->default(0);
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn("posts", "group_id")) {
                $table->foreignId('group_id')->nullable()->after("category_id")->constrained("groups")->cascadeOnDelete()->cascadeOnUpdate();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn("users", "strike")) {
                $table->dropColumn('strike');
            }
        });

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn("posts", "group_id")) {
                $table->dropConstrainedForeignId('group_id');
            }
        });
    }
};
