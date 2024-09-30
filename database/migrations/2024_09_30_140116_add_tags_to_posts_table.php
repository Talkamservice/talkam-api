<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AlterTagsColumnTypeInPostsTable extends Migration
{
    public function up()
    {
        // Temporarily rename the 'tags' column to 'tags_json'
        Schema::table('posts', function (Blueprint $table) {
            $table->renameColumn('tags', 'tags_json');
        });

        // Add a new 'tags' column with type TEXT
        Schema::table('posts', function (Blueprint $table) {
            $table->longText('tags')->nullable()->after('cover');
        });

        // Migrate existing JSON data to TEXT format
        DB::table('posts')->whereNotNull('tags_json')->update(['tags' => DB::raw('tags_json')]);

        // Drop the old 'tags_json' column
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('tags_json');
        });
    }

    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
}
