<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One movement in a company's prepaid session-bundle ledger (web §09): a
 * purchase (+N), a draw on booking (-1), or a refund on cancellation (+1).
 * The audit trail behind organizations.session_bundle_used.
 */
class OrganizationBundleEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "delta" => "integer",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function session()
    {
        return $this->belongsTo(TherapySession::class, "therapy_session_id");
    }
}
