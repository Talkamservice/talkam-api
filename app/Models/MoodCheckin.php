<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoodCheckin extends Model
{
    use HasFactory;

    protected $guarded = [];

    // checked_in_on is kept a plain Y-m-d string so the unique
    // (user_id, checked_in_on) upsert matches exactly.
    protected $casts = [
        'mood' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
