<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HookLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        "headers" => "array",
        "content" => "array",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->where("source", "LIKE", "%$key%")
                ->orwhereHas("user", function ($user) use ($key) {
                    $user->search($key);
                });
        });
    }
}
