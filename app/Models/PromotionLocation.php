<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionLocation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, "promotion_id");
    }

    public function country()
    {
        return $this->belongsTo(Country::class, "country_id");
    }
}
