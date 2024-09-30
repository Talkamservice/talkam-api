<?php

namespace App\Services\Finance\Plan;

use App\Constants\Finance\Plan\PlanConstants;
use App\Exceptions\Finance\PlanException;
use App\Models\PlanDuration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanDurationService
{

    public static function getById($id): PlanDuration

    {
        $plan_duration = PlanDuration::find($id);
        if (empty($plan_duration)) {
            throw new PlanException("Plan duration not found");
        }
        return $plan_duration;
    }

    public static function getByPlanId($id)
    {
        $plan_durations = PlanDuration::where("plan_id", $id)->latest()->get();
        return $plan_durations;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "price" => 'required|numeric|gt:-1',
            "discount" => 'nullable|numeric|gt:-1',
            "plan_id" => "required|exists:plans,id",
            "frequency" => 'required|string|' . Rule::in(PlanConstants::FREQUENCY_OPTIONS),
            "is_default" => "nullable|numeric|in:1,0"
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function save(array $data, $id = null)
    {
        $data = self::validate($data, $id);

        $data["duration"] = self::planDuration($data["frequency"]);

        if (!empty($id)) {
            $plan_duration = PlanDuration::find($id);
            $plan_duration->update($data);
        } else {
            $plan_duration =  PlanDuration::create($data);
        }

        return $plan_duration;
    }

    public function saveMultiple(array $data, $plan)
    {
        $data = $this->parseData($data);
        foreach ($data as $key => $data_) {
            $data_["plan_id"] = $plan->id;
            $this->save($data_);
        }

    }

    public function parseData(array $data)
    {
        $originalArray = [
            "price" => $data["price"],
            "frequency" => $data["frequency"],
            "discount" => $data["discount"],
        ];

        $newArray = [];

        foreach ($originalArray as $key => $values) {
            for ($i = 0; $i < count($values); $i++) {
                $newArray[$i][$key] = $values[$i];
            }
        }

        $first_data = $newArray[0] ?? null;
        
        if (count($first_data) > 0) {
            $first_data["is_default"] = 1;
            $newArray[0] = $first_data;
        }

        return $newArray;
    }

    public static function planDuration($frequency)
    {
        if ($frequency == PlanConstants::MONTHLY) {
            $duration = "30";
        }

        if ($frequency == PlanConstants::YEARLY) {
            $duration = "360";
        }

        return $duration;
    }
}
