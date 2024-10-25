<?php

namespace App\Services\Finance\Plan;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PlanBenefit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanBenefitService
{

    public static function getById($id): PlanBenefit
    {
        $plan_benefit = PlanBenefit::find($id);
        if (empty($plan_benefit)) {
            throw new ModelNotFoundException("Plan benefit not found");
        }
        return $plan_benefit;
    }

    public static function getByPlanId($id)
    {
        $plan_benefits = PlanBenefit::where("plan_id", $id)->latest()->get();
        return $plan_benefits;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "title" => 'nullable|string',
            "description" => 'nullable|string',
            "value" => 'nullable|string',
            "key" => 'nullable|string',
            "value_type" => 'nullable|string',
            "duration" => 'nullable|numeric|gt:-1',
            "plan_id" => "required|exists:plans,id|" . Rule::requiredIf(empty($id)),
            "status" => 'nullable|string|' . Rule::in(StatusConstants::ACTIVE_OPTIONS),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function save(array $data, $id = null)
    {
        $data = self::validate($data, $id);

        if (!empty($id)) {
            $plan_benefit = PlanBenefit::find($id);
            $plan_benefit->update($data);
        } else {
            $data["value"] ??= "Yes";
            $data["key"] ??= str_replace(" ", "-", strtolower($data["title"]));
            $data["status"] ??= StatusConstants::ACTIVE;
            $plan_benefit =  PlanBenefit::create($data);
        }

        return $plan_benefit;
    }
}
