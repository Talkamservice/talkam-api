<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use SoftDeletes, HasFactory;
    protected $guarded = [];

    public function exchangeCurrency()
    {
        return $this->hasOne(Currency::class, 'base_currency_id', 'exchange_currency_id');
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }

    public function minimal()
    {
        return new self([
            "id" => $this->id,
            "short_name" => $this->short_name,
            "symbol" => $this->logo,
        ]);
    }
}
