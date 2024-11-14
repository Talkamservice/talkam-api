<?php

namespace App\Models;

use App\Constants\Invitation\InvitationConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class Invitation extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    public function callbackUrl()
    {
        return route("web.admin.invite.handleCallback", ["source" => slugify($this->source), "uuid" => $this->uuid]);
    }

    public function deleteUrl(array $query = [])
    {
        return route("admin.invitation.delete", array_merge(["source" => slugify($this->source), "invitation" => $this->id], $query));
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, "invited_by");
    }

    public function role()
    {
        return $this->belongsTo(Role::class, "role_id");
    }

    public function inviterName()
    {
        return  $this->inviter->name;
    }

    public function scopeSource($query, $source = InvitationConstants::SUPER_ADMIN)
    {
        return $query->where("source", $source);
    }
}
