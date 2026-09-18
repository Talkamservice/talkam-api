<?php

namespace Tests\Feature\V2\Post;

use App\Models\Post;
use App\Models\RecentView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RecentViewService::list() used to build the pluck column as
 * "{$sort_key}_id" with $sort_key silently defaulting to null when the
 * `sort` query param was omitted — producing an invalid `_id` column and a
 * raw SQL 500 instead of a validation error.
 */
class RecentViewFetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_sort_returns_validation_error_not_sql_crash(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/recents/fetch")
            ->assertStatus(422)
            ->assertJsonValidationErrors(["sort"]);
    }

    public function test_invalid_sort_value_returns_validation_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/recents/fetch?sort=bogus")
            ->assertStatus(422)
            ->assertJsonValidationErrors(["sort"]);
    }

    public function test_valid_sort_returns_matching_records(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        RecentView::create(["user_id" => $user->id, "post_id" => $post->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/recents/fetch?sort=post")
            ->assertStatus(200)
            ->assertJsonPath("data.0.id", $post->id);
    }
}
