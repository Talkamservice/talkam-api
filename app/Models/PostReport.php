<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostReport extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user() {
        return $this->belongsTo(User::class, "user_id");
    }

    public function post() {
        return $this->belongsTo(Post::class, "post_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->where("first_name", "LIKE", "%$key%")
                ->orWhere("last_name", "LIKE", "%$key%")
                ->orWhere("email", "LIKE", "%$key%")
                ->orWhere("phone_number", "LIKE", "%$key%")
                ->orWhere("username", "LIKE", "%$key%");
        });
    }
}
