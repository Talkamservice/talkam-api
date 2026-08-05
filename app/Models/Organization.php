<?php

namespace App\Models;

use App\Constants\Business\OrganizationConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ["id"];

    protected $casts = [
        "therapist_access" => "boolean",
        "bundle_custom" => "boolean",
        "bench_topics" => "array",
        "verified_at" => "datetime",
        "employees_suspended_at" => "datetime",
        "cancels_at" => "datetime",
        "scheduled_deletion_at" => "datetime",
        "card_setup_at" => "datetime",
        "va_created_at" => "datetime",
        "kyc_consent_at" => "datetime",
        "credit_balance" => "decimal:2",
    ];

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function isVerified(): bool
    {
        return !empty($this->verified_at);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationConstants::STATUS_ACTIVE;
    }

    /** Seats consumed = active members + invites still outstanding. */
    public function seatsUsed(): int
    {
        $active = $this->members()
            ->where("status", OrganizationConstants::MEMBER_ACTIVE)
            ->count();

        $pending = $this->invitations()
            ->where("status", \App\Constants\General\StatusConstants::PENDING)
            ->count();

        return $active + $pending;
    }
}
