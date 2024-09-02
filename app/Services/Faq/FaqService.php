<?php

namespace App\Services\Faq;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\Faq;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FaqService
{

    public static function getById($id): Faq
    {
        $faq = Faq::find($id);
        if (empty($faq)) {
            throw new ModelNotFoundException("Faq not found");
        }
        return $faq;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "question" => "bail|required|string",
            "answer" => "bail|required|string",
            "url" => "bail|nulable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public  function store(array $data)
    {
        $data = self::validate($data);
        $faq =  Faq::create($data);
        return $faq;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $faq = self::getById($id);

        $faq->update($data);
        return $faq->refresh();
    }

    public function delete($faq_id)
    {
        $faq = self::getById($faq_id);
        $faq->delete();
    }

    public static function list()
    {
        $faqs = Faq::latest();
        return $faqs;
    }
}
