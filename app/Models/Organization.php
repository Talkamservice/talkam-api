<?php

namespace App\Models;

use App\Constants\Business\OrganizationConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected $casts = [
        "therapist_access" => "boolean",
        "bench_topics" => "array",
        "verified_at" => "datetime",
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
