<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guildline extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function group()
    {
        return $this->belongsTo(Group::class, "group_id");
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
    }
}
