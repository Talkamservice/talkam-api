<?php

namespace App\Services\Post;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\RecentView;
use App\Models\TrendingTag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecentViewService
{
    public static function getById($id): RecentView
    {
        $recent_view = RecentView::find($id);
        if (empty($recent_view)) {
            throw new ModelNotFoundException("Recent view not found");
        }
        return $recent_view;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "post_id" => "nullable|exists:posts,id",
            "category_id" => "nullable|exists:post_categories,id",
            "tag_id" => "nullable|exists:trending_tags,id",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["user_id"] = auth()->id();

        $recent_view = RecentView::updateOrCreate([
            "user_id" => $data["user_id"],
            "post_id" => $data["post_id"] ?? null,
            "category_id" => $data["category_id"] ?? null,
            "tag_id" => $data["tag_id"] ?? null,
        ], [
            "created_at" => now()
        ]);
        
        return $recent_view;
    }

    public static function delete($recent_view_id)
    {
        $post = self::getById($recent_view_id);
        $post->delete();
    }

    public static function list($user_id, array $data = [])
    {
        $builder = RecentView::with("user")->where("user_id", $user_id)->latest();

        if (!empty($key = $data["user_id"] ?? null)) {
            $builder->where("user_id", $key);
        }

        $sort_key = $data["sort"] ?? null;
        $record_key = "{$sort_key}_id";

        $record_ids = $builder->pluck($record_key)->toArray();

        $records = match ($sort_key) {
            'category' => PostCategory::status()->whereIn("id", $record_ids),
            'post' => Post::status()->whereIn("id", $record_ids),
            'tag' => TrendingTag::status()->whereIn("id", $record_ids),
            default => collect([]),
        };

        return [
            "key" => $sort_key,
            "records" => $records,
        ];
    }
}
