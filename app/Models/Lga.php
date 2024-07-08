<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lga extends Model
{
   use HasFactory;
   protected $guarded = ["id"];

   public function state()
   {
      return $this->belongsTo(State::class, "state_id", "id");
   }

   public function country()
   {
      return $this->belongsTo(Country::class, "country_id", "id");
   }

   public function scopeSearch($query, $key)
   {
      return $query->where("name", "LIKE", "%$key%");
   }
}
