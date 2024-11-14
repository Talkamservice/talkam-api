<?php

namespace App\Http\Controllers\Admin\PaymentGateways\Flutterwave;

use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FlutterwaveController extends Controller
{
   public $flutterwave_service;

   public function __construct(FlutterwaveService $flutterwave_service) {
      $this->flutterwave_service = $flutterwave_service;
   }

   // public function initiateSubscription(Request $request)
   // {
   //    try {
   //       $this->flutterwave_service->initiateSubscription($request);
   //       return back()->with(NotificationConstants::SUCCESS_MSG, "Payment made successfully");
   //   } catch (ValidationException $th) {
   //       throw $th;
   //   } catch (\Throwable $th) {
   //       throw $th;
   //       return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
   //   }
   // }
}
