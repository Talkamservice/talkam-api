<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanCountryPricing extends Model
{
    use HasFactory;
    protected $fillable = ['discount', 'lowered_cost', 'plan_id', 'country_id', 'status'];


    public function plan() 
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }

    public function country() 
    {
        return $this->belongsTo(Country::class);
    }

    public function formattedAmount()
    {
        return format_money($this->discount, 2, $this->plan->currency->symbol);
    }

    public function scopeSearch($query, $key)
    {
        return $query->where(function ($query) use ($key) {
            $query->where("lowered_cost", "LIKE", "%$key%")
                ->orWhere("discount", "LIKE", "%$key%")
                ->orWhere("status", "LIKE", "%$key%")
                ->orWhereHas("plan", function ($query) use ($key) {
                    $query->where("name", "LIKE", "%$key%");
                })->orWhereHas("country", function ($query) use ($key) {
                    $query->where("name", "LIKE", "%$key%");
                });
        });
    }
}
