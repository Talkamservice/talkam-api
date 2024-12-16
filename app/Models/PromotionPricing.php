<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionPricing extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function formattedAmount($field = "amount")
    {
        return format_money($this->$field, 2, $this->currency?->symbol ?? "$");
    }

    public function scopeSearch($query, $key = null)
    {
        if (!empty($key)) {
            $query->where(function ($query) use ($key) {
                $query->orWhere("status", "LIKE", "%$key%")
                    ->orWhere("amount", "LIKE", "%$key%")
                    ->orWhere("impressions", "LIKE", "%$key%")
                    ->orWhereHas("country", function ($query) use ($key) {
                        $query->where("name", "LIKE", "%$key%");
                    })
                    ->orWhereHas("currency", function ($query) use ($key) {
                        $query->where("name", "LIKE", "%$key%");
                    });
            });
        }

        return $query;
    }


    public function getCountryName()
    {
        return $this->country ? $this->country->name : null;
    }
}
