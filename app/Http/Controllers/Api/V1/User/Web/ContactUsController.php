<?php

namespace App\Http\Controllers\Api\V1\User\Web;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\ContactUs\ContactUsService;
use Exception;
use Illuminate\Http\Request;

class ContactUsController extends Controller
{
    protected $contact_us_service;

    public function __construct()
    {
        $this->contact_us_service = new ContactUsService;
    }

    public function save(Request $request)
    {
        try {
            $this->contact_us_service->store($request->all());
            return ApiHelper::validResponse("Message sent successfully");
        } catch (Exception $e) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE,  $request, $e);
        }
    }

}
