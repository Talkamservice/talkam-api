<?php

namespace App\Services\PrivacyPolicy;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PrivacyPolicy;
use App\Services\ActivityLog\ActivityLogService;
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
        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Created Privacy Policy")
            ->setDescription(auth()->user()?->full_name . " created privacy policy")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CREATED_PRIVACY_POLICY)
            ->setModel(PrivacyPolicy::class, $privacy_policy->id)
            ->setAdmin(auth()->user()->id)
            ->setData(
                [
                    "Privacy Policy" => $privacy_policy->refresh()->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();
        return $privacy_policy;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $privacy_policy = self::getById($id);
        $old_privacy_policy = $privacy_policy;
        $privacy_policy->update($data);
        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Updated Privacy Policy")
            ->setDescription(auth()->user()?->full_name . " updated privacy policy")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::UPDATED_TERMS_AND_CONDITION)
            ->setModel(PrivacyPolicy::class, $privacy_policy->id)
            ->setAdmin(auth()->user()->id)
            ->setData(
                [
                    "Privacy Policy" => $privacy_policy->refresh()->toArray()
                ],
                [
                    "Old Privacy Policy" => $old_privacy_policy->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();
        return $privacy_policy->refresh();
    }

    public function delete($privacy_policy_id)
    {
        $privacy_policy = self::getById($privacy_policy_id);
        $privacy_policy->delete();
    }
}
