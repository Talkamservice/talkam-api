<?php

namespace App\QueryBuilders\Session;

use App\Models\TherapySession;
use Illuminate\Http\Request;

class TherapySessionQueryBuilder
{
    public static function filterList(Request $request)
    {
        $builder = new TherapySession();

        if (!empty($therapist_id = $request->therapist_id)) {
            $builder = $builder->where("therapist_id", $therapist_id);
        }

        return $builder;
    }
}
