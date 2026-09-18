<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistCapacityRequest extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, "requested_by");
    }

    public function specialtyCategory()
    {
        return $this->belongsTo(PostCategory::class, "specialty_category_id");
    }
}
