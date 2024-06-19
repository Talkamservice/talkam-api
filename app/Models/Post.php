<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        "views_count" => "integer",
        "can_comment" => "integer",
        "is_anonymous" => "integer"
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function category()
    {
        return $this->belongsTo(PostCategory::class, "category_id");
    }

    public function attachments()
    {
        return $this->hasMany(PostAttachment::class, "post_id");
    }

    public function polls()
    {
        return $this->hasMany(PostPoll::class, "post_id");
    }
}
