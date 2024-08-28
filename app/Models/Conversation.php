<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        "is_anonymous" => 'boolean',
        "notification_status" => 'boolean',
    ];

    public function lastMessage()
    {
        return $this->hasOne(ConversationMember::class, "user_id", "id")->with("user")->latest();
    }

    public function members()
    {
        return $this->hasMany(ConversationMember::class, "conversation_id");
    }

    public function messages()
    {
        return $this->hasMany(Message::class, "conversation_id");
    }

    public function otherMembers()
    {
        return $this->hasMany(ConversationMember::class, "conversation_id", "id")->with("user")
            ->whereHas("user", function ($user) {
                $user->whereNotIn("id", [auth()->id()]);
            });
    }

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('user', function ($receiver) use ($search) {
                $receiver->search($search);
            })->orWhereHas("messages", function ($message) use ($search) {
                $message->search($search);
            });
        });
    }
}
