<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avatar extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function image()
    {
        return $this->belongsTo(File::class , "image_id" , "id");
    }

    public function imageUrl()
    {
        $image = $this->image;
        if(!empty($image)){
            return $image->url();
        }else{
            return null;
        }
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        $query->where("status", $status);
    }
}
