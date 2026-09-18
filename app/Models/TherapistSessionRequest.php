<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistSessionRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'preferred_at' => 'datetime',
        'proposed_starts_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function session()
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }
}
