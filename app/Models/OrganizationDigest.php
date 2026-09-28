<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Idempotency guard for one org's monthly usage-digest email (web §03). */
class OrganizationDigest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "period_start" => "date",
        "period_end" => "date",
        "sent_at" => "datetime",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
