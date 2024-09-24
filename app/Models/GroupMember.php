<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
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

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
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

    public function isSuspended()
    {
        if ($this->status === StatusConstants::INACTIVE) {
            return true;
        } else {
            return false;
        }
    }
}
