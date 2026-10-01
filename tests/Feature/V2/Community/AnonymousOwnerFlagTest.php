<?php

namespace Tests\Feature\V2\Community;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnonymousOwnerFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_sees_is_owner_on_their_anonymous_post_and_others_do_not(): void
    {
        $post = Post::factory()->create(["is_anonymous" => 1]);

        Sanctum::actingAs($post->user);
        $own = $this->getJson("/api/v2/user/posts/{$post->id}")->assertOk();
        $this->assertTrue($own->json("data.is_owner"));
        $this->assertNull($own->json("data.user"));

        Sanctum::actingAs(User::factory()->create());
        $other = $this->getJson("/api/v2/user/posts/{$post->id}")->assertOk();
        $this->assertFalse($other->json("data.is_owner"));
        $this->assertNull($other->json("data.user"));
    }

    public function test_is_owner_on_comments(): void
    {
        $post = Post::factory()->create();
        $comment = PostComment::create([
            "post_id" => $post->id,
            "user_id" => User::factory()->create()->id,
            "comment" => "anon reply",
            "is_anonymous" => 1,
        ]);

        Sanctum::actingAs($comment->user);
        $mine = $this->getJson("/api/v2/user/post-comments?post_id={$post->id}")->assertOk();
        $this->assertTrue($mine->json("data.0.is_owner"));
        $this->assertNull($mine->json("data.0.user"));

        Sanctum::actingAs(User::factory()->create());
        $theirs = $this->getJson("/api/v2/user/post-comments?post_id={$post->id}")->assertOk();
        $this->assertFalse($theirs->json("data.0.is_owner"));
    }

    public function test_only_anonymous_lists_just_my_anonymous_posts(): void
    {
        $anon = Post::factory()->create(["is_anonymous" => 1]);
        $public = Post::factory()->create(["user_id" => $anon->user_id, "is_anonymous" => 0]);
        $someone_elses_anon = Post::factory()->create(["is_anonymous" => 1]);

        Sanctum::actingAs($anon->user);
        $ids = collect($this->getJson("/api/v2/user/posts?only_anonymous=1")->assertOk()->json("data.data"))->pluck("id");

        $this->assertEquals([$anon->id], $ids->all());
        $this->assertNotContains($public->id, $ids->all());
        $this->assertNotContains($someone_elses_anon->id, $ids->all());
    }

    public function test_only_anonymous_cannot_be_used_to_unmask_another_author(): void
    {
        $anon = Post::factory()->create(["is_anonymous" => 1]);

        Sanctum::actingAs(User::factory()->create());
        $this->assertEmpty($this->getJson("/api/v2/user/posts?only_anonymous=1&user_id={$anon->user_id}")->assertOk()->json("data.data"));
    }
}
