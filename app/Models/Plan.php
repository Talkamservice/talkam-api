<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "feature_cards" => "array"
    ];

    public function benefits()
    {
        return $this->hasMany(PlanBenefit::class, "plan_id");
    }

    public function durations()
    {
        return $this->hasMany(PlanDuration::class, "plan_id");
    }

    public function defaultDuration()
    {
        $default_duration = $this->durations()->where("is_default", 1)->first();

        if (empty($default_duration)) {
           $default_duration = $this->durations()->first();
        }

        return $default_duration;
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }

    public function currency()
    {
        return $this->hasMany(Currency::class, 'currency_id');
    }
}
