<?php

namespace App\Services\Finance\Plan;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PlanScope;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanScopeService
{
    public static function getById($id): PlanScope
    {
        $plan_scope = PlanScope::find($id);
        if (empty($plan_scope)) {
            throw new ModelNotFoundException("Plan scope not found");
        }
        return $plan_scope;
    }

    public static function getByPlanId($id)
    {
        $plan_scopes = PlanScope::where("plan_id", $id)->latest()->get();
        return $plan_scopes;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "title" => 'required|string',
            "value" => 'required|string',
            "plan_id" => "required|exists:plans,id",
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
        $data["slug"] = slugify($data["title"]);

        if (!empty($id)) {
            $plan_scope = PlanScope::find($id);
            $plan_scope->update($data);
        } else {
            $plan_scope =  PlanScope::create($data);
        }

        return $plan_scope;
    }
}
