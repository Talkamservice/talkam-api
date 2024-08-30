<?php

namespace App\Services\Announcement;

use App\Constants\General\StatusConstants;
use App\Constants\Media\FileConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Announcement;
use App\Services\Media\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    protected $file_service;
    public function __construct(FileService $file_service)
    {
        $this->file_service = $file_service;
    }

    public static function getById($key, $column = "id")
    {
        $announcement = Announcement::where($column, $key)->first();
        if (empty($announcement)) {
            throw new ModelNotFoundException("Announcement not found");
        }
        return $announcement;
    }

    public static function validate(array $data, $id = null)
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => 'required|string|in:Group,Public',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            // 'status' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function send(array $data, $id = null)
    {
        // Validate the data
        $data = self::validate($data);
        $data['user_id'] = auth()->user()->id;

        if (!empty($banner_image = $data["banner_image"] ?? null)) {
            $data["banner_image"] = $this->file_service->saveFromFileIntoStorage($banner_image, FileConstants::ANNOUNCEMENT_BANNER_PATH, null, auth()->id());
        }

        if ($id) {
            $announcement = self::getById($id);
            // Update the announcement with validated data
            $announcement->update($data);
        } else {
            // Create new announcement
            $announcement = Announcement::create($data);
        }
        return $announcement;
    }


    public static function list(array $data = [])
    {
        $builder = Announcement::latest();

        if (!empty($status = $data["status"] ?? null)) {
            $builder = $builder->where("status", $status);
        }

        if (!empty($audience = $data["audience"] ?? null)) {
            $builder = $builder->where("audience", $audience);
        }

        return $builder;
    }


    public static function delete(string $id)
    {
        $announcement = self::getById($id);
        $announcement->delete();
    }

    public function changeStatus(Request $request, $id)
    {
        $status = $request->input('status');
        if (!in_array($status, [StatusConstants::ACTIVE, StatusConstants::INACTIVE])) {
            throw new InvalidRequestException("Invalid status provided");
        }

        $announcement = $this->getById($id);
        $announcement->update([
            "status" => $status
        ]);
        if (StatusConstants::ACTIVE) {
            $announcement->update([
                'published_at' => now(),
            ]);
        }
    }
}
