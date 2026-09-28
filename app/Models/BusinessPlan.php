<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPlan extends Model
{
    protected $guarded = ["id"];

    protected $casts = [
        "is_custom" => "boolean",
    ];

    public function tiers()
    {
        return $this->hasMany(BusinessPlanTier::class)->orderBy("sort_order");
    }

    public function features()
    {
        return $this->hasMany(BusinessPlanFeature::class)->orderBy("sort_order");
    }
}
