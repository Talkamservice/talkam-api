<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationTherapist extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected $casts = [
        "added_at" => "datetime",
        "removed_at" => "datetime",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function scopeActive($query)
    {
        return $query->where("status", "active");
    }
}
