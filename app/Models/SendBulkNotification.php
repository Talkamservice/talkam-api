<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SendBulkNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'type',
        'status',
        'schedule_date',
    ];

    // If the 'recipients' field is stored as JSON, this will cast it to an array
    protected $casts = [
        'recipients' => 'array',
    ];

    public function recipients() {
        return $this->belongsToMany(User::class, 'bulk_notification_user', 'send_bulk_notification_id', 'user_id');
    }
    
}
