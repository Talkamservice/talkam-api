<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function names()
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }

    public function getFullNameAttribute()
    {
        return $this->names();
    }

    public function getName()
    {
        return $this->username ?? $this->names();
    }

    public function interests()
    {
        return $this->hasMany(UserInterest::class, "user_id");
    }

    public function posts()
    {
        return $this->hasMany(Post::class, "user_id");
    }

    public function pins()
    {
        return $this->hasMany(Pin::class, "user_id");
    }


    public function blockedUsers()
    {
        return $this->hasMany(BlockedUser::class, "blocker_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->where("first_name", "LIKE", "%$key%")
                ->orWhere("last_name", "LIKE", "%$key%")
                ->orWhere("email", "LIKE", "%$key%")
                ->orWhere("phone_number", "LIKE", "%$key%")
                ->orWhere("username", "LIKE", "%$key%");
        });
    }

    public function isUser()
    {
        return $this->role == UserConstants::USER;
    }

    public function scopeRole($query, $role = UserConstants::USER)
    {
        return $query->where("role", $role);
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
    }

    public function isAdmin()
    {
        return $this->role == UserConstants::ADMIN;
    }

    public function avatar()
    {
        return $this->belongsTo(Avatar::class, 'avatar_id');
    }

    public function conversationMember()
    {
        return $this->belongsTo(ConversationMember::class, 'id', "user_id");
    }

    public function avatarUrl($type = null)
    {
        if (!empty($this->avatar)) {
            return $this->avatar;
        } elseif ($type == "white") {
            return asset("admin_assets/images/authentication/logo_white.svg");
        } else {
            return asset("admin_assets/images/authentication/logo.png");
        }
    }

    public function notificationPreference()
    {
        return $this->hasOne(NotificationPreference::class, 'user_id');
    }

    public function sendBulkNotifications() {
        return $this->belongsToMany(SendBulkNotification::class, 'bulk_notification_user', 'user_id', 'send_bulk_notification_id');
    }
    
    public function announcement()
    {
       return $this->hasMany(Announcement::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function isDisabled()
    {
        if (in_array(
            $this->accountDeactivation?->status,
            [StatusConstants::PROCESSING, StatusConstants::APPROVED]
        ) || in_array(
            $this->status,
            [StatusConstants::INACTIVE, StatusConstants::DISABLED, StatusConstants::BANNED]
        )) {
            return true;
        } else {
            return false;
        };
    }
}
