<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistWalletTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function therapist()
    {
        return $this->belongsTo(Therapist::class, 'therapist_id');
    }

    public function session()
    {
        return $this->belongsTo(TherapySession::class, 'session_id');
    }

    public function payout()
    {
        return $this->belongsTo(Payout::class, 'payout_id');
    }
}
