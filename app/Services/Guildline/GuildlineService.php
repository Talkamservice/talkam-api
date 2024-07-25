<?php

namespace App\Services\Guildline;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Guildline;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuildlineService
{
    public static function getById($id): Guildline
    {
        $guildline = Guildline::find($id);
        if (empty($guildline)) {
            throw new ModelNotFoundException("Guildline not found");
        }
        return $guildline;
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
        $guildline = Guildline::create($data);
        return $guildline;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $guildline = self::getById($id);
        $guildline->update($data);
        return $guildline->refresh();
    }

    public static function delete($guildline_id)
    {
        $guildline = self::getById($guildline_id);
        $guildline->delete();
    }

    public static function list(array $data = [])
    {
        $builder = Guildline::latest();
        if (!empty($key = $data["group_id"] ?? null)) {
            $builder = $builder->where("group_id", $key);
        } else {
            $builder = $builder->whereNull("group_id");
        }
        return $builder;
    }
}
