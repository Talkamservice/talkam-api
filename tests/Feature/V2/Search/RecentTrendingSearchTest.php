<?php

namespace Tests\Feature\V2\Search;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecentTrendingSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_logs_query_into_recent_searches(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Post::factory()->create(["title" => "grief and healing"]);

        $this->getJson("/api/v2/user/search?sort=post&search=grief")->assertStatus(200);

        $words = collect($this->getJson("/api/v2/user/search/recent")->assertStatus(200)->json("data"))
            ->pluck("word");
        // TrendingSearchResource title-cases words.
        $this->assertTrue($words->contains("Grief"));
    }

    public function test_delete_removes_single_recent_search(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Post::factory()->create(["title" => "grief and healing"]);

        $this->getJson("/api/v2/user/search?sort=post&search=grief");
        $this->getJson("/api/v2/user/search?sort=post&search=healing");

        $recent = collect($this->getJson("/api/v2/user/search/recent")->json("data"));
        $target = $recent->firstWhere("word", "Grief");

        $this->deleteJson("/api/v2/user/search/{$target['id']}/delete")->assertStatus(200);

        $words = collect($this->getJson("/api/v2/user/search/recent")->json("data"))->pluck("word");
        $this->assertFalse($words->contains("Grief"));
        $this->assertTrue($words->contains("Healing"));
    }

    public function test_trending_returns_envelope_list(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/user/search/trending")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/search/recent")->assertStatus(401);
        $this->getJson("/api/v2/user/search/trending")->assertStatus(401);
    }
}
