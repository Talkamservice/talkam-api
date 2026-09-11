<?php

namespace Tests\Feature\V2\Community;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Models\UserInterest;
use App\Models\UserPostReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * §15 delta: therapists skip interest onboarding, so for_you must fall
 * back to the engagement (featured) ranking for zero-interest users.
 */
class ForYouFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_interests_receives_engagement_ranked_results(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $plain = Post::factory()->create(["created_at" => now()->subHour()]);
        $engaged = Post::factory()->create(["created_at" => now()->subDay()]);
        foreach (range(1, 3) as $i) {
            UserPostReaction::create([
                "user_id" => User::factory()->create()->id,
                "post_id" => $engaged->id,
                "action" => "Like",
            ]);
        }

        $ids = collect($this->getJson("/api/v2/user/posts?tab=for_you")->assertStatus(200)->json("data.data"))
            ->pluck("id");

        $this->assertTrue($ids->search($engaged->id) < $ids->search($plain->id));
    }

    public function test_user_with_interests_keeps_interest_ranking(): void
    {
        $user = User::factory()->create();
        $topic = PostCategory::factory()->interestTopic()->create();
        UserInterest::create(["user_id" => $user->id, "category_id" => $topic->id]);
        Sanctum::actingAs($user);

        $interest_post = Post::factory()->create([
            "category_id" => $topic->id,
            "created_at" => now()->subDay(),
        ]);
        $engaged_other = Post::factory()->create(["created_at" => now()]);
        UserPostReaction::create([
            "user_id" => User::factory()->create()->id,
            "post_id" => $engaged_other->id,
            "action" => "Like",
        ]);

        $ids = collect($this->getJson("/api/v2/user/posts?tab=for_you")->json("data.data"))->pluck("id");

        $this->assertTrue($ids->search($interest_post->id) < $ids->search($engaged_other->id));
    }
}
