<?php

namespace Tests\Feature\V2\Community;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostViewsCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_post_increments_views_count_for_other_users(): void
    {
        $post = Post::factory()->create();
        $updated_at = $post->fresh()->updated_at;
        Sanctum::actingAs(User::factory()->create());

        $first = $this->getJson("/api/v2/user/posts/{$post->id}")->assertOk();
        $second = $this->getJson("/api/v2/user/posts/{$post->id}")->assertOk();

        $this->assertSame(1, $first->json("data.views_count"));
        $this->assertSame(2, $second->json("data.views_count"));
        $this->assertSame(2, $post->fresh()->views_count);
        $this->assertEquals($updated_at, $post->fresh()->updated_at);
    }

    public function test_the_authors_own_opens_are_not_counted(): void
    {
        $post = Post::factory()->create();
        Sanctum::actingAs($post->user);

        $this->getJson("/api/v2/user/posts/{$post->id}")->assertOk();

        $this->assertSame(0, $post->fresh()->views_count);
    }
}
