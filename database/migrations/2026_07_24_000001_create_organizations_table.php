<?php

use App\Constants\Business\OrganizationConstants;
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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // AppServiceProvider sets a 500-char default; indexed columns need
            // explicit lengths to stay inside MySQL's 3072-byte key limit.
            $table->string('slug', 191)->unique();
            $table->string('domain', 191)->unique();
            $table->string('industry', 100)->nullable();
            $table->string('headcount_band', 50)->nullable();
            $table->string('logo')->nullable();
            $table->string('hr_contact_email', 191)->nullable();
            $table->string('status', 30)->default(OrganizationConstants::STATUS_PENDING_VERIFICATION);
            $table->unsignedInteger('seats_licensed')->default(0);
            $table->boolean('therapist_access')->default(true);
            $table->unsignedInteger('session_bundle_sessions')->default(0);
            $table->string('pay_method', 20)->nullable();
            $table->json('bench_topics')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
