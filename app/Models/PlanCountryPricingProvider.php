<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanCountryPricingProvider extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function planDuration()
    {
        return $this->belongsTo(PlanDuration::class, 'plan_duration_id');
    }

    public function planCountryPricing()
    {
        return $this->belongsTo(PlanCountryPricing::class, 'plan_country_pricing_id');
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }

    public function formattedAmount()
    {
        return format_money($this->price, 2, $this->plan?->currency?->symbol ?? "$");
    }

    public function getPercentage()
    {
        $percentage = divideNumber($this->price, $this->planDuration?->price) * 100;
        return $percentage;
    }
}
