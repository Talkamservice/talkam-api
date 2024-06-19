<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function post()
    {
        return $this->belongsTo(Post::class, "user_id");
    }

    public function parent()
    {
        return $this->belongsTo(self::class, "parent_id");
    }

    public function children()
    {
        return $this->hasMany(self::class, "parent_id");
    }
}
