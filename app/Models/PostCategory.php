<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user() {
        return $this->belongsTo(User::class, "user_id");
    }

    public function post() {
        return $this->belongsTo(Post::class, "post_id");
    }

    public function imageUrl()
    {
        return $this->image ?? null;
    }
}
