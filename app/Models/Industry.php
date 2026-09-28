<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A business industry offered on the B2B signup form (web §01). Admin-managed.
 */
class Industry extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "slug",
        "sort_order",
        "status",
    ];

    protected static function booted(): void
    {
        // Keep a URL-safe slug in step with the name unless one is set explicitly.
        static::saving(function (Industry $industry) {
            if (empty($industry->slug) && !empty($industry->name)) {
                $industry->slug = Str::slug($industry->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where("status", StatusConstants::ACTIVE);
    }
}
