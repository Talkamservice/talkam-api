<?php

namespace App\Models;

use App\Constants\Therapist\TherapistConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapySession extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'hold_expires_at' => 'datetime',
        'reminded_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'started_at' => 'datetime',
        'client_joined_at' => 'datetime',
        'therapist_joined_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class, 'therapist_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function review()
    {
        return $this->hasOne(TherapistReview::class, 'session_id');
    }

    /**
     * Statuses that hold a slot: an unexpired pending_payment or anything
     * confirmed/underway.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', [
                TherapistConstants::SESSION_CONFIRMED,
                TherapistConstants::SESSION_IN_PROGRESS,
                TherapistConstants::SESSION_COMPLETED,
            ])->orWhere(function ($hold) {
                $hold->where('status', TherapistConstants::SESSION_PENDING_PAYMENT)
                    ->where('hold_expires_at', '>', now());
            });
        });
    }
}
