<?php

namespace App\Models;

use App\Constants\Business\OrganizationConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMember extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected $casts = [
        "activated_at" => "datetime",
        "deactivated_at" => "datetime",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, "invited_by");
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationConstants::MEMBER_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where("status", OrganizationConstants::MEMBER_ACTIVE);
    }
}
