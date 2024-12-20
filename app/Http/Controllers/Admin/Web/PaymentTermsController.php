<?php

namespace App\Http\Controllers\Admin\Web;

use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\PaymentTerm;
use App\Services\Finance\Payment\PaymentTermService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentTermsController extends Controller
{
    protected $payment_term_service;
    public function __construct()
    {
        $this->payment_term_service = new PaymentTermService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $payment_term = PaymentTerm::latest()->first();
        return view("dashboards.admin.pages.finance.payment_terms.create", [
            "payment_term" => $payment_term,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->payment_term_service->store($request->all());
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Term and Condition updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
