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
            "group_id" => "nullable|exists:groups,id|" . Rule::requiredIf(empty($id)),
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
        $post = Guildline::create($data);
        return $post;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);
        return $post->refresh();
    }

    public static function delete($guildline_id)
    {
        $post = self::getById($guildline_id);
        $post->delete();
    }

    public static function list($group_id = null)
    {
        $builder = Guildline::latest();
        if (!empty($group_id)) {
            $builder = $builder->where("group_id", $group_id);
        }
        return $builder;
    }
}
