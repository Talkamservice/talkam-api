<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyPolicy extends Model
{
    use HasFactory;

    protected $guarded = [];

    // The web §06 structured legal document (mobile/v1 read `body` only).
    protected $casts = [
        "document" => "array",
    ];
}
