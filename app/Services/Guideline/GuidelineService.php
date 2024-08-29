<?php

namespace App\Services\Guideline;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Guideline;
use App\Models\SendBulkNotification;
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
        return $guideline;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $guideline = self::getById($id);
        $guideline->update($data);
        return $guideline->refresh();
    }

    public static function delete($guideline_id)
    {
        $guideline = self::getById($guideline_id);
        $guideline->delete();
    }

    public static function list(array $data = [])
    {
        $builder = SendBulkNotification::latest();
        if (!empty($key = $data["group_id"] ?? null)) {
            $builder = $builder->where("group_id", $key);
        } else {
            $builder = $builder->whereNull("group_id");
        }
        return $builder;
    }
}
