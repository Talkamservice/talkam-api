<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanDuration extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function plan()
    {
        return $this->belongsTo(Plan::class, "plan_id");
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, "plan_duration_id");
    }
}
