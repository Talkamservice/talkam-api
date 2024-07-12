<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentView extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function posts()
    {
        return $this->hasMany(Post::class, "id", "post_id");
    }

    public function categories()
    {
        return $this->hasMany(PostCategory::class, "id", "category_id");
    }

    public function tags()
    {
        return $this->hasMany(TrendingTag::class, "id", "tag_id");
    }
}
