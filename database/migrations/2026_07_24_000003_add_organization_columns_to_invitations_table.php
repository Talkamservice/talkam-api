<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Org invites ride the existing invitations table, exactly as §05 group
     * invites do via group_id. v1 admin invites and group invites leave
     * organization_id null; every org query filters on it.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('group_id')
                ->constrained('organizations')->cascadeOnDelete();
            $table->string('invite_role', 20)->nullable()->after('organization_id');
            $table->string('department', 100)->nullable()->after('invite_role');
            $table->timestamp('opened_at')->nullable()->after('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['invite_role', 'department', 'opened_at']);
        });
    }
};
