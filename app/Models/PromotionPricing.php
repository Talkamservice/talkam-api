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

    public function formattedAmount()
    {
        return format_money($this->amount, 2, $this->currency?->symbol ?? "$");
    }

    public function scopeSearch($query, $key)
    {
        return $query->where(function ($query) use ($key) {
            $query->where("status", "LIKE", "%$key%")
                ->where("amount", "LIKE", "%$key%")
                ->where("impressions", "LIKE", "%$key%")
                ->whereHas("country", function ($query) use ($key) {
                    $query->where("name", "LIKE", "%$key%");
                })
                ->whereHas("currency", function ($query) use ($key) {
                    $query->where("name", "LIKE", "%$key%");
                });
        });
    }

    public function getCountryName()
    {
        return $this->country ? $this->country->name : null;
    }
}
