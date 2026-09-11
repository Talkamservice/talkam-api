<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A TalkAM Journal article (web §05). Editorial content, separate from the
 * community `Post` feed.
 */
class Article extends Model
{
    use HasFactory;

    const STATUS_PUBLISHED = "published";

    protected $fillable = [
        "slug",
        "category",
        "tone",
        "cover",
        "title",
        "excerpt",
        "author",
        "author_initials",
        "author_role",
        "author_bio",
        "read_time",
        "display_date",
        "body",
        "sort_order",
        "published_at",
        "status",
    ];

    protected $casts = [
        "body" => "array",
        "published_at" => "datetime",
        "sort_order" => "integer",
    ];

    public function scopePublished($query)
    {
        return $query->where("status", self::STATUS_PUBLISHED);
    }

    public function getRouteKeyName(): string
    {
        return "slug";
    }
}
