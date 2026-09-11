<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\User\DataExportService;
use Exception;

class DataExportController extends Controller
{
    public $export_service;
    function __construct()
    {
        $this->export_service = new DataExportService;
    }

    public function store()
    {
        try {
            $export = $this->export_service->request(auth()->user());
            return ApiHelper::validResponse("Data export requested successfully", [
                "id" => $export->id,
                "status" => $export->status,
            ]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function latest()
    {
        try {
            $data = DataExportService::latest(auth()->user());
            return ApiHelper::validResponse("Data export state returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
