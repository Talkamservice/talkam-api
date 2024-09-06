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
        Schema::table('faqs', function (Blueprint $table) {
            $table->foreignId('faq_category_id')->nullable()->after('id')->constrained('faq_categories')->cascadeOnDelete();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            if (Schema::hasColumns("faqs", ["faq_category_id"])) {
                $table->dropConstrainedForeignId('faq_category_id');
            }
        });
    }
    
};
