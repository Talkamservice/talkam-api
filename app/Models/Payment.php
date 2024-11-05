<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "metadata" => "array",
    ];

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
        return $query->where(function ($q) use ($key) {
            $q->whereHas("user", function ($user) use ($key) {
                $user->search($key);
            });
        });
    }

}
