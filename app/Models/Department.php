<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $guarded = ["id"];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
