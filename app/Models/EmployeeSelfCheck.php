<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSelfCheck extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected $casts = [
        "score" => "integer",
        "answered_at" => "datetime",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Present so the model is complete — deliberately NOT used by any
     * admin-facing query. The org aggregate reads organization_id only.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
