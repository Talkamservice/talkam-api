<?php

namespace App\Services\Finance\Payment;

use App\Constants\ActivityLog\ActivitiesConstants;
use App\Constants\ActivityLog\ActivityLogConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PaymentTerm;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentTermService
{

    public static function getById($id): PaymentTerm
    {
        $payment_term = PaymentTerm::find($id);
        if (empty($payment_term)) {
            throw new ModelNotFoundException("Payment term not found");
        }
        return $payment_term;
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
        $payment_term =  PaymentTerm::create($data);
        // Log the activity
        (new ActivityLogService)
            ->setEvent("created")
            ->setTitle("Created Payment Terms")
            ->setDescription((auth()->user()?->email) . " created payment terms")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::CREATED_PAYMENT_TERM)
            ->setModel(PaymentTerm::class, $payment_term->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(["Payment Terms" => $payment_term->refresh()->toArray()])
            ->setUrl(request()->fullUrl())
            ->log();
        return $payment_term;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $payment_term = self::getById($id);

        $old_payment_term =  $payment_term;

        $payment_term->update($data);
        // Log the activity
        (new ActivityLogService)
            ->setEvent("updated")
            ->setTitle("Updated Payment Terms")
            ->setDescription((auth()->user()?->email) . " updated payment terms")
            ->setType(ActivityLogConstants::SYSTEM_URL_TYPE)
            ->setActivity(ActivitiesConstants::UPDATED_PAYMENT_TERM)
            ->setModel(PaymentTerm::class, $payment_term->id)
            ->setAdmin(auth()->user()?->id)
            ->setData(
                [
                    "Payment Terms" => $payment_term->refresh()->toArray()
                ],
                [
                    "Old Payment Terms" => $old_payment_term->toArray()
                ]
            )
            ->setUrl(request()->fullUrl())
            ->log();
        return $payment_term->refresh();
    }

    public function delete($payment_term_id)
    {
        $payment_term = self::getById($payment_term_id);
        $payment_term->delete();
    }


    public static function list()
    {
        $payment_terms = PaymentTerm::latest();
        return $payment_terms;
    }
}
