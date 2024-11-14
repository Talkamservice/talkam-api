<?php

namespace App\QueryBuilders\User;

use App\Models\User;
use Illuminate\Http\Request;

class UserQueryBuilder
{
    public static function filterList(Request $request)
    {
        $builder = new User();

        if (!empty($key = $request->search)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $request->status)) {
            $builder = $builder->status($key);
        }

        if (!empty($key = $request->from) && empty($request->to)) {
            $builder = $builder->whereDate("created_at", ">=", $key);
        }

        if (empty($request->from) && !empty($key = $request->to)) {
            $builder = $builder->whereDate("created_at", "<=", $key);
        }

        if (!empty($from = $request->from) && !empty($to = $request->to)) {
            $builder = $builder->whereBetween("created_at", [$from, $to]);
        }

        return $builder;
    }
}
