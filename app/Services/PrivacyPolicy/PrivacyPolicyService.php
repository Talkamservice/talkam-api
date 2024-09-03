<?php

namespace App\Services\PrivacyPolicy;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\PrivacyPolicy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrivacyPolicyService
{

    public static function getById($id): PrivacyPolicy
    {
        $privacy_policy = PrivacyPolicy::find($id);
        if (empty($privacy_policy)) {
            throw new ModelNotFoundException("Policy not found");
        }
        return $privacy_policy;
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
        $privacy_policy =  PrivacyPolicy::create($data);
        return $privacy_policy;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $privacy_policy = self::getById($id);

        $privacy_policy->update($data);
        return $privacy_policy->refresh();
    }

    public function delete($privacy_policy_id)
    {
        $privacy_policy = self::getById($privacy_policy_id);
        $privacy_policy->delete();
    }
}
