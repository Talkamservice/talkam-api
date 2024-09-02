<?php

namespace App\Http\Controllers\Admin\Web;

use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\TermAndCondition;
use App\Services\TermAndCondition\TermAndConditionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TermAndConditionController extends Controller
{
    protected $term_and_condition_service;

    public function __construct()
    {
        $this->term_and_condition_service = new TermAndConditionService;
    }

    public function create()
    {
        $term_and_condition = TermAndCondition::latest()->first();
        return view("dashboards.admin.pages.term_and_condition.create", [
            "term_and_condition" => $term_and_condition,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->term_and_condition_service->store($request->all());
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Term and Condition updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
