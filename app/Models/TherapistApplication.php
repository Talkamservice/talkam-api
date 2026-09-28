<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistApplication extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'session_formats' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function documents()
    {
        return $this->hasMany(TherapistDocument::class, 'application_id');
    }

    public function specialties()
    {
        return $this->hasMany(TherapistSpecialty::class, 'application_id');
    }
}
