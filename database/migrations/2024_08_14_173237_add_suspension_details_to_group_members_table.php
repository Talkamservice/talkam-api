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
        Schema::table('group_members', function (Blueprint $table) {
            $table->integer('suspension_count')->default(0)->after('status');
            $table->dateTime('suspension_end')->nullable()->after('suspension_count');
            $table->boolean('banned')->default(false)->after('suspension_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('suspension_count');
            $table->dropColumn('suspension_end');
            $table->dropColumn('banned');

        });
    }
};
