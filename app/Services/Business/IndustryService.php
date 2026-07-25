<?php

namespace App\Services\Business;

use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Industry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin management of the B2B signup industries (web §01). Mirrors the FAQ
 * category service shape; the public signup form reads the active set via
 * GET business/industries.
 */
class IndustryService
{
    public static function getById($id): Industry
    {
        $industry = Industry::find($id);

        if (empty($industry)) {
            throw new ModelNotFoundException("Industry not found");
        }

        return $industry;
    }

    public static function validate(array $data, $id = null): array
    {
        $validator = Validator::make($data, [
            "name" => ["bail", "required", "string", "max:120", Rule::unique("industries", "name")->ignore($id)],
            "status" => ["nullable", "string", Rule::in(array_keys(StatusConstants::ACTIVE_OPTIONS))],
            "sort_order" => ["nullable", "integer", "min:0", "max:100000"],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function store(array $data): Industry
    {
        $data = self::validate($data);
        $data["slug"] = Str::slug($data["name"]);
        $data["status"] = $data["status"] ?? StatusConstants::ACTIVE;

        return Industry::create($data);
    }

    public function update(array $data, $id): Industry
    {
        $data = self::validate($data, $id);
        $industry = self::getById($id);
        $data["slug"] = Str::slug($data["name"]);

        $industry->update($data);

        return $industry->refresh();
    }

    public function delete($id): void
    {
        self::getById($id)->delete();
    }

    public static function list()
    {
        return Industry::orderBy("sort_order")->orderBy("name");
    }
}
