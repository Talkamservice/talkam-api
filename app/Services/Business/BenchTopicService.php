<?php

namespace App\Services\Business;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\PostCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Curates which therapist-specialty categories a company can prioritise on the
 * "Preview your therapist bench" screen (web §4b).
 *
 * There is deliberately ONE taxonomy: bench topics are the same
 * `post_categories` rows (type = interest_topic) that therapists tag as their
 * specialties and the mobile therapist directory filters on. So featuring a
 * category here, tagging it on a therapist, and matching a company's bench all
 * point at the same row — no second list to drift. Admins simply flag which of
 * those categories surface on the bench, and in what order.
 */
class BenchTopicService
{
    /** The interest-topic categories live under this parent (see InterestTopicSeeder). */
    public static function parent(): PostCategory
    {
        return PostCategory::firstOrCreate(
            ["name" => "Mental Health", "category_id" => null],
            ["description" => "Mental health topics", "status" => StatusConstants::ACTIVE]
        );
    }

    public static function getById($id): PostCategory
    {
        $topic = PostCategory::where("id", $id)
            ->where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->first();

        if (empty($topic)) {
            throw new ModelNotFoundException("Bench topic not found");
        }

        return $topic;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "name" => [
                "bail", "required", "string", "max:120",
                Rule::unique("post_categories", "name")
                    ->where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
                    ->ignore($id),
            ],
            "status" => ["nullable", "string", Rule::in(array_keys(StatusConstants::ACTIVE_OPTIONS))],
            "bench_sort" => ["nullable", "integer", "min:0", "max:100000"],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function store(array $data): PostCategory
    {
        $data = self::validate($data);

        // Creating a bench specialty adds it to the shared taxonomy, so it is
        // also offered to therapists and the mobile app — that is the point of
        // unifying on categories.
        return PostCategory::create([
            "name" => $data["name"],
            "category_id" => self::parent()->id,
            "type" => PostCategoryConstants::TYPE_INTEREST_TOPIC,
            "status" => $data["status"] ?? StatusConstants::ACTIVE,
            "uuid" => MethodsHelper::getRandomToken(10),
            "is_bench_featured" => self::truthy($data["is_bench_featured"] ?? true),
            "bench_sort" => $data["bench_sort"] ?? 0,
        ]);
    }

    public function update(array $data, $id): PostCategory
    {
        $data = self::validate($data, $id);
        $topic = self::getById($id);

        $topic->update([
            "name" => $data["name"],
            "status" => $data["status"] ?? $topic->status,
            "is_bench_featured" => self::truthy($data["is_bench_featured"] ?? false),
            "bench_sort" => $data["bench_sort"] ?? $topic->bench_sort,
        ]);

        return $topic->refresh();
    }

    /**
     * "Delete" only removes the topic from the bench — it never destroys the
     * category, which therapists and the mobile app still rely on.
     */
    public function delete($id): void
    {
        self::getById($id)->update(["is_bench_featured" => false]);
    }

    /** Admin index: every interest-topic category, bench order first. */
    public static function list()
    {
        return PostCategory::where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->orderBy("bench_sort")
            ->orderBy("name");
    }

    /**
     * The picker the bench screen renders: [{key, label}, ...] where `key` is
     * the category id, so a company's saved selection joins straight onto a
     * therapist's specialty `category_id`. Falls back to every active interest
     * topic if nothing is featured, so the screen is never empty.
     */
    public static function activeList(): array
    {
        $base = PostCategory::where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->where("status", StatusConstants::ACTIVE);

        $featured = (clone $base)->where("is_bench_featured", true)
            ->orderBy("bench_sort")
            ->orderBy("name")
            ->get(["id", "name"]);

        $rows = $featured->isNotEmpty()
            ? $featured
            : $base->orderBy("name")->get(["id", "name"]);

        return $rows
            ->map(fn (PostCategory $c) => ["key" => (string) $c->id, "label" => $c->name])
            ->all();
    }

    private static function truthy($value): bool
    {
        return in_array($value, [true, 1, "1", "on", "true", "yes"], true);
    }
}
