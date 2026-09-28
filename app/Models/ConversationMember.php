<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationMember extends Model
{
    use HasFactory;
    protected $guarded = [];

    // v2 (§16) per-member state columns; v1 never reads them.
    protected $casts = [
        "is_typing" => "boolean",
        "is_muted" => "boolean",
        "last_seen_at" => "datetime",
        "last_typing_at" => "datetime",
        "muted_at" => "datetime",
        "muted_until" => "datetime",
        "archived_at" => "datetime",
        "starred_at" => "datetime",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, "conversation_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->whereHas("user", function ($user) use ($key) {
                $user->search($key);
            });
        });
    }
}
