<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistSpecialty extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(TherapistApplication::class, 'application_id');
    }

    public function category()
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }
}
