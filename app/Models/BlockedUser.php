<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function blocker()
    {
        return $this->belongsTo(User::class, "blocker_id");
    }

    public function blockedUser()
    {
        return $this->belongsTo(User::class, "blocked_user_id");
    }
}
