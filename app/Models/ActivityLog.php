<?php

namespace App\Models;

use App\Constants\ActivityLog\ActivityLogConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function admin()
    {
        return $this->belongsTo(Admin::class, "admin_id");
    }

    public function parse($field)
    {
        $field = $this->$field;
        if (is_null($field) || is_array($field)) {
            return "ss";
        }

        $data = json_decode($field, true);
        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = json_encode($value);
            }
        }

        return $data;
    }

    public function scopeSearch($query, $value)
    {
        $query->with(["admin"])->where(function ($query) use ($value) {
            $query->whereRaw("CONCAT(title, ' ', description) LIKE ?", ["%$value%"])
                ->orWhereHas("admin", function ($admin) use ($value) {
                    $admin->search($value);
                });
        });
    }

    public function canShowMeta()   
    {
        return ActivityLogConstants::canShowMetadata($this);
    }
}
