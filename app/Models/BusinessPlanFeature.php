<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPlanFeature extends Model
{
    protected $guarded = ["id"];

    public function plan()
    {
        return $this->belongsTo(BusinessPlan::class, "business_plan_id");
    }
}
