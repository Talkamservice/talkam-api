<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Journal newsletter subscriber (web §05). Public capture — only the email,
 * source and status are ever written.
 */
class NewsletterSubscriber extends Model
{
    use HasFactory;

    const STATUS_SUBSCRIBED = "subscribed";

    protected $fillable = [
        "email",
        "source",
        "status",
    ];
}
