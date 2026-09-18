<?php

namespace Tests\Feature\V2\Search;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_tab_returns_envelope_with_pagination_totals(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Post::factory()->count(2)->create(["title" => "anxiety support thread"]);

        $this->getJson("/api/v2/user/search?sort=post&search=anxiety")
            ->assertStatus(200)
            ->assertJson(["success" => true])
            ->assertJsonPath("data.pagination_meta.total", 2);
    }

    public function test_people_tab_includes_is_following_flag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $followed = User::factory()->create(["first_name" => "Adaeze", "username" => "adaeze_1"]);
        $not_followed = User::factory()->create(["first_name" => "Adaeze", "username" => "adaeze_2"]);
        UserFollow::create(["follower_id" => $user->id, "followed_id" => $followed->id]);

        $results = collect($this->getJson("/api/v2/user/search?sort=people&search=Adaeze")
            ->assertStatus(200)->json("data.data"));

        $this->assertTrue($results->firstWhere("id", $followed->id)["is_following"]);
        $this->assertFalse($results->firstWhere("id", $not_followed->id)["is_following"]);
    }

    public function test_groups_tab_includes_is_member_flag(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $joined = Group::factory()->create(["name" => "Anxiety Circle"]);
        $not_joined = Group::factory()->create(["name" => "Anxiety Space"]);
        GroupMember::factory()->create(["group_id" => $joined->id, "user_id" => $user->id]);

        $results = collect($this->getJson("/api/v2/user/search?sort=groups&search=Anxiety")
            ->assertStatus(200)->json("data.data"));

        $this->assertTrue($results->firstWhere("id", $joined->id)["is_member"]);
        $this->assertFalse($results->firstWhere("id", $not_joined->id)["is_member"]);
    }

    public function test_related_topics_match_query_against_topic_names(): void
    {
        Sanctum::actingAs(User::factory()->create());
        PostCategory::factory()->interestTopic()->create(["name" => "Anxiety"]);
        PostCategory::factory()->interestTopic()->create(["name" => "Depression"]);

        $topics = collect($this->getJson("/api/v2/user/search?sort=post&search=anxiety")
            ->json("data.related_topics"))->pluck("name");

        $this->assertTrue($topics->contains("Anxiety"));
        $this->assertFalse($topics->contains("Depression"));
    }

    public function test_related_topics_empty_when_no_topic_matches(): void
    {
        Sanctum::actingAs(User::factory()->create());
        PostCategory::factory()->interestTopic()->create(["name" => "Anxiety"]);

        $this->getJson("/api/v2/user/search?sort=post&search=zzzunmatched")
            ->assertJsonPath("data.related_topics", []);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/search?sort=post&search=x")->assertStatus(401);
    }
}
