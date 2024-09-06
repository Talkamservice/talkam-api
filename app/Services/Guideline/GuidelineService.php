<?php

namespace App\Services\Guideline;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Guideline;
use App\Models\SendBulkNotification;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuidelineService
{
    public static function getById($id): Guideline
    {
        $guideline = Guideline::find($id);
        if (empty($guideline)) {
            throw new ModelNotFoundException("Guideline not found");
        }
        return $guideline;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "group_id" => "nullable|exists:groups,id",
            "title" => "required|string",
            "description" => "nullable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $guideline = Guideline::create($data);
        // Log the activity
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Guideline Created")
            ->setDescription(auth()->user()?->full_name . " created a guideline")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::GUIDELINE_CREATED)
            ->setModel(Guideline::class, $guideline->id)
            ->setAdmin(auth()->user()->id)
            ->setData(
                ["Guidline" => $guideline->refresh()->toArray()]
            )
            ->setUrl(request()->fullUrl())
            ->log();
        return $guideline;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $guideline = self::getById($id);
        $old_guideline = $guideline;
        $guideline->update($data);

        // Log the activity
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Guideline Created")
            ->setDescription(auth()->user()?->full_name . " created a guideline")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::GUIDELINE_CREATED)
            ->setModel(Guideline::class, $guideline->id)
            ->setAdmin(auth()->user()->id)
            ->setData(
                ["Old Guidline" => $old_guideline->toArray()],
                ["Guidline" => $guideline->refresh()->toArray()]
            )
            ->setUrl(request()->fullUrl())
            ->log();

        return $guideline->refresh();
    }

    public static function delete($guideline_id)
    {
        $guideline = self::getById($guideline_id);
        $old_guideline = $guideline;
        $guideline->delete();
        // Log the activity
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Guideline Created")
            ->setDescription(auth()->user()?->full_name . " created a guideline")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::GUIDELINE_CREATED)
            ->setModel(Guideline::class, $guideline->id)
            ->setAdmin(auth()->user()->id)
            ->setData(
                ["Old Guidline" => $old_guideline->toArray()],
            )
            ->setUrl(request()->fullUrl())
            ->log();
    }

    public static function list(array $data = [])
    {
        $builder = Guideline::latest();
        if (!empty($key = $data["group_id"] ?? null)) {
            $builder = $builder->where("group_id", $key);
        } else {
            $builder = $builder->whereNull("group_id");
        }
        return $builder;
    }
}
