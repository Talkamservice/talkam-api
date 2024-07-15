<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupExecutive extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function group()
    {
        return $this->belongsTo(Group::class, "group_id");
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->whereHas("user", function ($user) use ($key) {
                $user->search($key);
            })
            ->orwhereHas("group", function ($group) use ($key) {
                $group->search($key);
            });
        });
    }
}
