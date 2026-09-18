<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrivacySetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'anonymous_mode' => 'boolean',
        'read_receipts' => 'boolean',
        'activity_status' => 'boolean',
        'two_factor_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
