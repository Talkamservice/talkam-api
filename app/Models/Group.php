<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        "tags" => "array"
    ];

    public function category()
    {
        return $this->belongsTo(PostCategory::class, "category_id");
    }

    public function creator()
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class, "group_id");
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->where("name", "LIKE", "%$key%")
                ->orWhere("description", "LIKE", "%$key%")
                ->orWhere("uuid", "LIKE", "%$key%")
                ->orwhereHas("creator", function ($user) use ($key) {
                    $user->search($key);
                })
                ->orwhereHas("category", function ($category) use ($key) {
                    $category->search($key);
                });
        });
    }
}
