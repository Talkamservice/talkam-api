<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistDocument extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(TherapistApplication::class, 'application_id');
    }

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}
