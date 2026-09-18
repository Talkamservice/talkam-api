<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $guarded = [];

    // The provider token must never be serialized to clients.
    protected $hidden = ['token'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
