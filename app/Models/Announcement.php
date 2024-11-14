<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'audience',
        'banner_image',
        'status',
        'published_at',
        'expired_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function bannerUrl()
    {
        return $this->banner_image ?? null;
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
