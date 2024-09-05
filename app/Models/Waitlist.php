<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waitlist extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }
}
