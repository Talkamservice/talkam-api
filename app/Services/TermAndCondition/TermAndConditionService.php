<?php

namespace App\Services\TermAndCondition;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\TermAndCondition;
use App\Services\ActivityLog\ActivityLogService;
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
        // Log the activity
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Created Terms and Condition")
            ->setDescription(auth()->user()?->full_name . " created term and condition")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CREATED_TERMS_AND_CONDITION)
            ->setModel(TermAndCondition::class, $term_and_condition->id)
            ->setAdmin(auth()->user()->id)
            ->setData(["Terms and Condition" => $term_and_condition->refresh()->toArray()])
            ->setUrl(request()->fullUrl())
            ->log();
        return $term_and_condition;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $term_and_condition = self::getById($id);

        $old_term_and_condition =  $term_and_condition;

        $term_and_condition->update($data);
        // Log the activity
        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Updated Terms and Condition")
            ->setDescription(auth()->user()?->full_name . " updated term and condition")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::UPDATED_TERMS_AND_CONDITION)
            ->setModel(TermAndCondition::class, $term_and_condition->id)
            ->setAdmin(auth()->user()->id)
            ->setData(
                [
                    "Terms and Condition" => $term_and_condition->refresh()->toArray()
                ],
                [
                    "Old Terms and Condition" => $old_term_and_condition->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();
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
