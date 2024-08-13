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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId("category_id")->nullable()->constrained("post_categories")->cascadeOnDelete();
            $table->string("name");
            $table->string("uuid")->unique();
            $table->string("description")->nullable(); // Purpose
            $table->string("image")->nullable();
            $table->longText("about")->nullable(); // Information
            $table->json("tags")->nullable();
            $table->string("status")->nullable()->default(StatusConstants::ACTIVE);
            $table->integer("can_post")->default(1);
            $table->string("group_access")->nullable()->default(StatusConstants::OPENED);
            $table->foreignId("created_by")->nullable()->constrained("users")->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
