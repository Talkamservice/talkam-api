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
}
