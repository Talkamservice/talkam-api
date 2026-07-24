<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company invoice for a billing period (web §07). Tenant-scoped via
 * organization_id; the web only ever reads these.
 */
class OrganizationInvoice extends Model
{
    use HasFactory;

    const STATUS_PAID = "paid";
    const STATUS_DUE = "due";

    protected $fillable = [
        "organization_id",
        "reference",
        "period_start",
        "period_end",
        "seats",
        "amount",
        "status",
        "issued_at",
        "due_at",
    ];

    protected $casts = [
        "period_start" => "date",
        "period_end" => "date",
        "issued_at" => "datetime",
        "due_at" => "datetime",
        "amount" => "decimal:2",
        "seats" => "integer",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
