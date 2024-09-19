<?php

namespace App\Services\Faq;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Faq;
use App\Services\ActivityLog\ActivityLogService;
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
            'faq_category_id' => 'nullable|string|exists:faq_categories,id',
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
        (new ActivityLogService)
        ->setEvent("created")
        ->setTitle("Created FAQ")
        ->setDescription((auth()->user()->email) . " created FAQ")
        ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
        ->setActivity(ActivitiesConstants::CREATED_FAQ)
        ->setModel(Faq::class, $faq->id)
        ->setAdmin(auth()->user()?->id)
        ->setData(
            [
                "FAQ" => $faq->refresh()->toArray()
            ])
        ->setUrl(request()->fullUrl())
        ->log();
        return $faq;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $faq = self::getById($id);
        $old_faq = $faq;

        $faq->update($data);
        (new ActivityLogService)
        ->setEvent("updated")
        ->setTitle("Updated FAQ")
        ->setDescription((auth()->user()->email) . " updated FAQ")
        ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
        ->setActivity(ActivitiesConstants::UPDATED_FAQ)
        ->setModel(Faq::class, $faq->id)
        ->setAdmin(auth()->user()?->id)
        ->setData(
            [
                "FAQ" => $faq->refresh()->toArray()
            ],
            [
                "Old FAQ " => $old_faq->toArray()
            ]
        )
        ->setUrl(request()->fullUrl())
        ->log();
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
