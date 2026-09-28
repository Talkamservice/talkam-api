<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_groups_in_standard_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Group::factory()->count(2)->create();

        $this->getJson("/api/v2/user/groups")
            ->assertStatus(200)
            ->assertJson(["success" => true, "code" => 200])
            ->assertJsonStructure(["message", "data" => ["data", "pagination_meta"], "success", "code"]);
    }

    public function test_category_filter_returns_only_matching_groups(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $topic = PostCategory::factory()->interestTopic()->create();
        $in_topic = Group::factory()->create(["category_id" => $topic->id]);
        $other = Group::factory()->create();

        $ids = collect($this->getJson("/api/v2/user/groups?category_id={$topic->id}")->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($in_topic->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_guest_listing_returns_only_open_groups(): void
    {
        $open = Group::factory()->create();
        $closed = Group::factory()->closed()->create();

        $ids = collect($this->getJson("/api/v2/user/groups")->assertStatus(200)->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->contains($open->id));
        $this->assertFalse($ids->contains($closed->id));
    }

    public function test_show_returns_about_tab_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $group = Group::factory()->create();

        $this->getJson("/api/v2/user/groups/{$group->id}")
            ->assertStatus(200)
            ->assertJsonPath("data.id", $group->id)
            ->assertJsonPath("data.description", $group->description)
            ->assertJsonPath("data.about", $group->about)
            ->assertJsonPath("data.group_access", "Opened")
            ->assertJsonStructure(["data" => ["total_members", "owner"]]);
    }
}
