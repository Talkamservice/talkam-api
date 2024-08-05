<?php

namespace App\Services\Post;

use App\Constants\Post\PostConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Group;
use App\Models\Post;
use App\Models\TrendingSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SearchService
{
    public static function getById($id): TrendingSearch
    {
        $trending_search = TrendingSearch::find($id);
        if (empty($trending_search)) {
            throw new ModelNotFoundException("Recent view not found");
        }
        return $trending_search;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "word" => "required|string",
            "user_id" => "nullable|exists:users,id",
            "category_id" => "nullable|exists:post_categories,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["user_id"] ??= auth("sanctum")->id();

        $trending_search = (!empty($data["user_id"])) ? TrendingSearch::updateOrCreate([
            "user_id" => $data["user_id"] ?? null,
            "category_id" => $data["category_id"] ?? null,
            "word" => strtolower($data["word"])
        ]) : TrendingSearch::create([
            "user_id" => $data["user_id"] ?? null,
            "category_id" => $data["category_id"] ?? null,
            "word" => strtolower($data["word"])
        ]);

        return $trending_search ?? null;
    }

    public static function delete($search_id)
    {
        $search = self::getById($search_id);
        $search->delete();
    }

    public static function trending()
    {
        $builder = TrendingSearch::whereBetween('created_at', [now()->copy()->subDays(2)->format("Y-m-d H:i:s"), now()->format("Y-m-d H:i:s")])
            ->select('word', DB::raw('count(*) as occurrences'))
            ->groupBy('word')
            ->orderByDesc("occurrences");

        return $builder;
    }


    public static function recent(array $data = [])
    {
        $builder = TrendingSearch::query();

        if (!empty($key = $data["user_id"] ?? null)) {
            $builder->where("user_id", $key);
        }

        $builder = $builder->latest();

        return $builder;
    }

    public static function search(array $data = [])
    {
        $validator = Validator::make($data, [
            "sort" => "required|string|in:post,group,media",
            "search" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        self::create([
            "user_id" => $data["user_id"] ?? auth("sanctum")->id(),
            "category_id" => $data["category_id"] ?? null,
            "word" => $data["search"]
        ]);

        $records = match ($data["sort"]) {
            'post' => Post::status()->search($data["search"]),
            'group' => Group::status()->search($data["search"]),
            'media' => Post::status()->search($data["search"])->where("type", PostConstants::FILE),
            default => collect([]),
        };

        return [
            "key" => $data["sort"],
            "records" => $records,
        ];
    }

    public static function suggestions(array $data = [])
    {
        $builder = TrendingSearch::query();

        if (!empty($key = $data["search"] ?? null)) {
            $builder->search($key);
        }

        $builder = $builder->latest();
        return $builder;
    }
}
