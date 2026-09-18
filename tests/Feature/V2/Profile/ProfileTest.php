<?php

namespace Tests\Feature\V2\Profile;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_profile_header_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v2/user/me")
            ->assertStatus(200)
            ->assertJsonPath("data.username", $user->username)
            ->assertJsonStructure(["data" => ["avatar", "name", "username", "onboarding"]]);
    }

    public function test_own_posts_tab_includes_anonymous_posts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $anon = Post::factory()->create(["user_id" => $user->id, "is_anonymous" => 1]);

        $ids = collect($this->getJson("/api/v2/user/posts?user_id={$user->id}")->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($anon->id));
    }

    public function test_other_viewer_excludes_anonymous_posts(): void
    {
        $author = User::factory()->create();
        $anon = Post::factory()->create(["user_id" => $author->id, "is_anonymous" => 1]);
        $public = Post::factory()->create(["user_id" => $author->id]);

        Sanctum::actingAs(User::factory()->create());
        $ids = collect($this->getJson("/api/v2/user/posts?user_id={$author->id}")->json("data.data"))
            ->pluck("id");

        $this->assertFalse($ids->contains($anon->id));
        $this->assertTrue($ids->contains($public->id));
    }
}
