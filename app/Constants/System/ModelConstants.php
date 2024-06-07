<?php

namespace App\Constants\System;

use Illuminate\Database\Eloquent\Model;

class ModelConstants
{
    public static function parseModelKey(Model $model)
    {
        $class = explode('\\', $model::class);
        $key = strtolower(end($class)) . "_id";
        return $key;
    }
}
