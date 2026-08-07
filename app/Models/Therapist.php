<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Therapist extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'session_formats' => 'array',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(TherapistReview::class, 'therapist_id');
    }

    public function sessions()
    {
        return $this->hasMany(TherapySession::class, 'therapist_id');
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, $key)
    {
        return $query->whereHas('user', function ($q) use ($key) {
            $q->search($key);
        });
    }
}
