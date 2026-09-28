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
        'client_left_at' => 'datetime',
        'therapist_left_at' => 'datetime',
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

    public function note()
    {
        return $this->hasOne(SessionNote::class, 'session_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Statuses that hold a slot: an unexpired pending_payment, an org-covered
     * pending_payment (SessionBookingService::create() deliberately leaves
     * hold_expires_at null there — there's nothing to pay, so no expiry —
     * meaning it holds the slot indefinitely until the therapist acts on
     * it), or anything confirmed/underway. Before this null check, an
     * org-covered booking sitting in pending_payment didn't occupy its slot
     * at all: TherapistSlotService kept offering the same slot to other
     * clients, and the transaction-level clash guard in
     * SessionBookingService::create() (built on this same scope) didn't
     * catch it either — a real double-booking, not just a display glitch.
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
                    ->where(function ($expiry) {
                        $expiry->whereNull('hold_expires_at')
                            ->orWhere('hold_expires_at', '>', now());
                    });
            });
        });
    }
}
