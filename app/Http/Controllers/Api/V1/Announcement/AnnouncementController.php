<?php

namespace App\Http\Controllers\Api\V1\Announcement;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Announcement\AnnouncementResource;
use App\Services\Announcement\AnnouncementService;
use Exception;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    protected $announcement_service;
    public function __construct(AnnouncementService $announcement_service)
    {
        $this->announcement_service = $announcement_service;
    }
    public function index(Request $request)
    {
        try {
            $announcements = $this->announcement_service->list($request->all())->where('status', StatusConstants::ACTIVE)->get();
            $data = AnnouncementResource::collection($announcements);
            return ApiHelper::validResponse("Announcements returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $announcement = $this->announcement_service->getById($id);
            $data = AnnouncementResource::make($announcement);
            return ApiHelper::validResponse("Announcement returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
