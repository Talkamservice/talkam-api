<?php

namespace App\Services\TermAndCondition;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\TermAndCondition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TermAndConditionService
{

    public static function getById($id): TermAndCondition
    {
        $term_and_condition = TermAndCondition::find($id);
        if (empty($term_and_condition)) {
            throw new ModelNotFoundException("T&C not found");
        }
        return $term_and_condition;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "body" => "bail|required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public  function store(array $data)
    {
        $data = self::validate($data);
        $term_and_condition =  TermAndCondition::create($data);
        return $term_and_condition;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $term_and_condition = self::getById($id);

        $term_and_condition->update($data);
        return $term_and_condition->refresh();
    }

    public function delete($term_and_condition_id)
    {
        $term_and_condition = self::getById($term_and_condition_id);
        $term_and_condition->delete();
    }


    public static function list()
    {
        $terms_and_conditions = TermAndCondition::latest();
        return $terms_and_conditions;
    }
}
