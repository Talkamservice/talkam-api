<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataExportRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}
