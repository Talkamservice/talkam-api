<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionReschedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'old_starts_at' => 'datetime',
        'new_starts_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
