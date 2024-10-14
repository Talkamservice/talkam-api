<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;
    protected $guarded = ["id"];

    public function state()
    {
        return $this->hasMany(State::class, "country_id");
    }

    public function scopeSearch($query, $key)
    {
        return $query->where("name", "LIKE", "%$key%");
    }
}
