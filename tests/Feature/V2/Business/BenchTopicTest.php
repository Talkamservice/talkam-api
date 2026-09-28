<?php

namespace Tests\Feature\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PostCategory;
use App\Models\Therapist;
use App\Models\TherapistApplication;
use App\Models\TherapistSpecialty;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\BenchTopicService;
use App\Services\Business\OrgRosterService;
use App\Services\Business\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Therapist-bench specialties (web §4b) unified onto the interest-topic
 * taxonomy: the same post_categories therapists tag and the mobile directory
 * matches on. Admins curate which ones appear on the bench.
 */
class BenchTopicTest extends TestCase
{
    use RefreshDatabase;

    private function interestTopic(string $name, array $attrs = []): PostCategory
    {
        return PostCategory::create(array_merge([
            "name" => $name,
            "uuid" => "cat-" . strtolower(str_replace(" ", "-", $name)),
            "type" => PostCategoryConstants::TYPE_INTEREST_TOPIC,
            "status" => StatusConstants::ACTIVE,
        ], $attrs));
    }

    public function test_store_creates_a_featured_interest_topic_under_mental_health(): void
    {
        $topic = (new BenchTopicService)->store(["name" => "Burnout"]);

        $this->assertSame(PostCategoryConstants::TYPE_INTEREST_TOPIC, $topic->type);
        $this->assertTrue((bool) $topic->is_bench_featured);
        // Parented under the shared "Mental Health" node like every interest topic.
        $this->assertSame("Mental Health", $topic->parentCategory->name);
    }

    public function test_store_rejects_a_duplicate_specialty_name(): void
    {
        $this->interestTopic("Anxiety");

        $this->expectException(ValidationException::class);
        (new BenchTopicService)->store(["name" => "Anxiety"]);
    }

    public function test_update_can_rename_reorder_and_unfeature(): void
    {
        $topic = $this->interestTopic("Grief", ["is_bench_featured" => true, "bench_sort" => 2]);

        (new BenchTopicService)->update(
            ["name" => "Grief & Loss", "bench_sort" => 5, "is_bench_featured" => "0"],
            $topic->id
        );

        $topic->refresh();
        $this->assertSame("Grief & Loss", $topic->name);
        $this->assertSame(5, (int) $topic->bench_sort);
        $this->assertFalse((bool) $topic->is_bench_featured);
    }

    public function test_delete_only_unfeatures_and_keeps_the_category(): void
    {
        $topic = $this->interestTopic("Relationships", ["is_bench_featured" => true]);

        (new BenchTopicService)->delete($topic->id);

        // The category still exists (therapists/app depend on it) — just off the bench.
        $this->assertDatabaseHas("post_categories", ["id" => $topic->id]);
        $this->assertFalse((bool) $topic->refresh()->is_bench_featured);
    }

    public function test_active_list_returns_featured_in_order_as_id_keyed_pairs(): void
    {
        $b = $this->interestTopic("Depression", ["is_bench_featured" => true, "bench_sort" => 1]);
        $a = $this->interestTopic("Anxiety", ["is_bench_featured" => true, "bench_sort" => 0]);
        $this->interestTopic("Psychosis"); // not featured — excluded

        $list = BenchTopicService::activeList();

        $this->assertSame(
            [
                ["key" => (string) $a->id, "label" => "Anxiety"],
                ["key" => (string) $b->id, "label" => "Depression"],
            ],
            $list
        );
    }

    public function test_active_list_falls_back_to_all_active_when_none_featured(): void
    {
        $this->interestTopic("Anxiety");
        $this->interestTopic("Grief");

        $labels = collect(BenchTopicService::activeList())->pluck("label")->all();

        $this->assertEqualsCanonicalizing(["Anxiety", "Grief"], $labels);
    }

    public function test_seeder_features_the_default_six(): void
    {
        $this->seed(\Database\Seeders\BenchTopicSeeder::class);

        $featured = PostCategory::where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->where("is_bench_featured", true)
            ->pluck("name")
            ->all();

        $this->assertEqualsCanonicalizing(
            ["Anxiety", "Depression", "Relationships", "Work Stress", "Grief", "PTSD"],
            $featured
        );
    }

    public function test_save_bench_accepts_interest_topic_ids_and_rejects_others(): void
    {
        $topic = $this->interestTopic("Anxiety");
        $notATopic = PostCategory::create([
            "name" => "Random Category",
            "uuid" => "cat-random",
            "status" => StatusConstants::ACTIVE,
        ]);
        $org = Organization::factory()->create();

        (new OrganizationService)->saveBench($org, ["bench_topics" => [(string) $topic->id]]);
        $this->assertSame([(string) $topic->id], $org->refresh()->bench_topics);

        $this->expectException(ValidationException::class);
        (new OrganizationService)->saveBench($org, ["bench_topics" => [(string) $notATopic->id]]);
    }

    public function test_roster_marks_a_therapist_in_network_by_exact_category_match(): void
    {
        $anxiety = $this->interestTopic("Anxiety");
        $grief = $this->interestTopic("Grief");

        // A therapist whose specialty is Anxiety.
        $user = User::factory()->create();
        Therapist::factory()->create(["user_id" => $user->id]);
        $application = TherapistApplication::factory()->create([
            "user_id" => $user->id,
            "status" => TherapistConstants::STATUS_APPROVED,
        ]);
        TherapistSpecialty::create([
            "application_id" => $application->id,
            "category_id" => $anxiety->id,
        ]);

        // Org that prioritises Anxiety → therapist is in-network.
        $matching = Organization::factory()->create(["bench_topics" => [(string) $anxiety->id]]);
        $row = collect(OrgRosterService::therapists($matching)["therapists"])
            ->firstWhere("id", Therapist::first()->id);
        $this->assertTrue($row["in_network"]);

        // Org that prioritises only Grief → same therapist is out of network.
        $other = Organization::factory()->create(["bench_topics" => [(string) $grief->id]]);
        $row = collect(OrgRosterService::therapists($other)["therapists"])
            ->firstWhere("id", Therapist::first()->id);
        $this->assertFalse($row["in_network"]);
    }

    public function test_roster_always_includes_the_orgs_own_therapist(): void
    {
        $anxiety = $this->interestTopic("Anxiety");

        // A provider the org brought in: a therapist member with no specialties.
        $user = User::factory()->create();
        Therapist::factory()->create(["user_id" => $user->id, "verified_at" => null]);

        // The org prioritises Anxiety, which this therapist does NOT match...
        $org = Organization::factory()->create(["bench_topics" => [(string) $anxiety->id]]);
        OrganizationMember::factory()->create([
            "organization_id" => $org->id,
            "user_id" => $user->id,
            "role" => OrganizationConstants::ROLE_THERAPIST,
            "status" => OrganizationConstants::MEMBER_ACTIVE,
        ]);

        $row = collect(OrgRosterService::therapists($org)["therapists"])
            ->firstWhere("id", Therapist::first()->id);

        // ...yet they still belong to "My Therapists" as the org's own provider.
        $this->assertTrue($row["is_own"]);
        $this->assertTrue($row["in_network"]);
    }

    /** N active employee members, so the cohort clears the privacy floor. */
    private function orgWithEmployees(int $count): array
    {
        $org = Organization::factory()->create();
        $members = collect(range(1, $count))->map(function () use ($org) {
            $user = User::factory()->create();
            OrganizationMember::factory()->create([
                "organization_id" => $org->id,
                "user_id" => $user->id,
            ]);

            return $user;
        });

        return [$org, $members];
    }

    public function test_team_sessions_counts_completed_member_sessions_cumulatively(): void
    {
        [$org, $members] = $this->orgWithEmployees(5); // cohort 5 >= floor
        $therapist = Therapist::factory()->create();

        // Completed sessions by members, across different months — all count.
        TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id, "user_id" => $members[0]->id]);
        TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id, "user_id" => $members[0]->id, "starts_at" => now()->subMonths(4)]);
        TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id, "user_id" => $members[1]->id]);
        // A member's booking that never happened — excluded (not completed).
        TherapySession::factory()->pending()->create(["therapist_id" => $therapist->id, "user_id" => $members[2]->id]);
        // A non-member's completed session — excluded (not the team).
        TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id, "user_id" => User::factory()->create()->id]);

        $row = collect(OrgRosterService::therapists($org)["therapists"])
            ->firstWhere("id", $therapist->id);

        // 3 completed, member-owned, regardless of month.
        $this->assertSame(3, $row["team_sessions"]);
    }

    public function test_team_sessions_is_suppressed_below_the_cohort_floor(): void
    {
        [$org, $members] = $this->orgWithEmployees(1); // cohort 1 < floor
        $therapist = Therapist::factory()->create();
        TherapySession::factory()->completed()->create(["therapist_id" => $therapist->id, "user_id" => $members[0]->id]);

        $row = collect(OrgRosterService::therapists($org)["therapists"])
            ->firstWhere("id", $therapist->id);

        // A one-person cohort could deanonymise the single employee — withheld.
        $this->assertNull($row["team_sessions"]);
    }
}
