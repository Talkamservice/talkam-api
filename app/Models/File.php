<?php

namespace App\Models;

use App\Helpers\MethodsHelper;
use App\Services\Media\FileService;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{

    protected $guarded = [];

    public function user(){
        return $this->belongsTo(User::class, "user_id", "id");
    }

    public function cleanDelete($id = null, $delete = true)
    {
        $id = $id ?? $this->id;
        FileService::cleanDelete($id, $delete);
    }

    public function url()
    {
        if (!empty($path = $this->path)) {
            return MethodsHelper::readFileUrl("encrypt", $path);
        }
    }
}
