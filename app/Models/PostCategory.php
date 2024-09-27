<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function posts()
    {
        return $this->hasMany(Post::class, "category_id");
    }

    public function imageUrl()
    {
        return $this->image ?? null;
    }

    public function scopeSearch($query, $key)
    {
        $key = strtolower($key); // Convert search key to lowercase
        $query->where(function ($query) use ($key) {
            $query->whereRaw("LOWER(name) LIKE ?", ["%$key%"])
                ->orWhereRaw("LOWER(description) LIKE ?", ["%$key%"]);
        });
    }


    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
    }

    public function trendingTags()
    {
        return $this->hasMany(TrendingTag::class, "category_id");
    }

    public function interests()
    {
        return $this->hasMany(UserInterest::class, "category_id");
    }

    public function parentCategory()
    {
        return $this->belongsTo(self::class, "category_id");
    }
}
