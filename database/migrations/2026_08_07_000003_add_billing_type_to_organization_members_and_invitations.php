<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing preference for an org-added ("own") therapist — talkam_billed vs
 * self_billed. Carried on the invitation while pending, copied onto the
 * membership row on accept (the same place `department` already lives).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('billing_type')->nullable()->after('department');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('billing_type')->nullable()->after('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });
    }
};
