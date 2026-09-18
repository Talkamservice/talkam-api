<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    // SoftDeletes: flagged §16/§3b exception — the deleted_at column has
    // existed since the v1 migration; the trait makes deletes soft (rows
    // retained, client-visible behavior identical).
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        "read" => 'boolean',
        "is_pinned" => 'boolean',
        "is_forwarded" => 'boolean',
        "delivered_at" => 'datetime',
        "read_at" => 'datetime',
        "edited_at" => 'datetime',
        "pinned_at" => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, "conversation_id");
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, "message_id");
    }

    public function edits()
    {
        return $this->hasMany(MessageEdit::class, "message_id");
    }

    public function sender()
    {
        return $this->belongsTo(User::class, "sender_id");
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, "receiver_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->where("message", "LIKE", "%$key%")
                ->orWhere("asset_url", "LIKE", "%$key%");
        });
    }
}
