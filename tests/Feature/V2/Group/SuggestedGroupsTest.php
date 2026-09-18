<?php

namespace Tests\Feature\V2\Group;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuggestedGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithInterest(): array
    {
        $user = User::factory()->create();
        $topic = PostCategory::factory()->interestTopic()->create();
        UserInterest::create(["user_id" => $user->id, "category_id" => $topic->id]);

        return [$user, $topic];
    }

    public function test_interest_matching_groups_rank_first(): void
    {
        [$user, $topic] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $matching = Group::factory()->create(["category_id" => $topic->id]);
        $other = Group::factory()->create();
        // The non-matching group is bigger — interest match must still win.
        GroupMember::factory()->count(3)->create(["group_id" => $other->id]);

        $ids = collect($this->getJson("/api/v2/user/groups/suggested")->assertStatus(200)->json("data.data"))
            ->pluck("id");
        $this->assertTrue($ids->search($matching->id) < $ids->search($other->id));
    }

    public function test_ties_ordered_by_member_count(): void
    {
        [$user, $topic] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $small = Group::factory()->create(["category_id" => $topic->id]);
        $large = Group::factory()->create(["category_id" => $topic->id]);
        GroupMember::factory()->count(3)->create(["group_id" => $large->id]);
        GroupMember::factory()->count(1)->create(["group_id" => $small->id]);

        $ids = collect($this->getJson("/api/v2/user/groups/suggested")->json("data.data"))->pluck("id");
        $this->assertTrue($ids->search($large->id) < $ids->search($small->id));
    }

    public function test_joined_groups_excluded(): void
    {
        [$user, $topic] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $joined = Group::factory()->create(["category_id" => $topic->id]);
        GroupMember::factory()->create(["group_id" => $joined->id, "user_id" => $user->id]);

        $ids = collect($this->getJson("/api/v2/user/groups/suggested")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($joined->id));
    }

    public function test_closed_groups_excluded(): void
    {
        [$user, $topic] = $this->userWithInterest();
        Sanctum::actingAs($user);
        $closed = Group::factory()->closed()->create(["category_id" => $topic->id]);

        $ids = collect($this->getJson("/api/v2/user/groups/suggested")->json("data.data"))->pluck("id");
        $this->assertFalse($ids->contains($closed->id));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson("/api/v2/user/groups/suggested")->assertStatus(401);
    }
}
