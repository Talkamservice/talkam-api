<?php

namespace App\Services\Media;

use App\Constants\Media\FileConstants;
use App\Helpers\MethodsHelper;
use App\Models\File as FileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FileService
{

    public bool $move_file = true;
    public bool $add_watermark = false;
    public $watermark_path = null;
    public FileModel $fileModel;
    protected $fileName = null;
    protected $image_width = null;
    protected $image_height = null;
    public function __construct()
    {
        $this->fileModel = new FileModel;
    }

    public function setMoveFile(bool $value)
    {
        $this->move_file = $value;
        return $this;
    }

    public function setWatermark(bool $value, $path = null)
    {
        $this->add_watermark = $value;
        $this->watermark_path = $path;
        return $this;
    }

    public function setImageSize(int $width  = null, int $height = null)
    {
        $this->image_width = $width;
        $this->image_height = $height;
        return $this;
    }

    public function setFilename($value)
    {
        $this->fileName = $value;
        return $this;
    }

    public function save($filepath, $type, $file_id = null, $user_id = null): FileModel
    {

        $info = pathinfo($filepath);
        $mime = $info["extension"];
        $filename = uniqid() . "." . $mime;
        $path = "app/media/$type";
        $full_path = storage_path($path);

        MethodsHelper::withDir($full_path);

        if ($this->move_file) {
            File::move($filepath, "$full_path/$filename");
        } else {
            File::copy($filepath, "$full_path/$filename");
        }

        $path_to_store =  "$path/$filename";

        // Permanent file path
        $file_full_path = storage_path($path_to_store);
        $file_size = filesize($file_full_path);
        $meta_info = [
            "name" => $this->fileName,
            "size" => $file_size,
            "formatted_size" => MethodsHelper::bytesToHuman($file_size)
        ];

        if (in_array(trim($mime), ["jpg", "jpeg", "png"])) {
            $image_info = getimagesize($file_full_path);
            $meta_info["width"] = $image_info[0];
            $meta_info["height"] = $image_info[1];
            $meta_info["length"] = null;
        }

        // if (in_array(trim($mime), ["avi", "mp4", "mkv"])) {
        //     $video_info = VideoProcessor::videoDimensions($file_full_path);
        //     $meta_info["width"] = $video_info["width"];
        //     $meta_info["height"] = $video_info["height"];
        //     $meta_info["length"] = VideoProcessor::mediaLength($file_full_path);
        // }

        // if (in_array(trim($mime), ["mp3", "m4a"])) {
        //     $meta_info["length"] = VideoProcessor::mediaLength($file_full_path);
        // }

        $file = $this->fileModel->find($file_id);
        if (!empty($file_id) && !empty($file)) {
            $this->fileModel->cleanDelete($file_id, false);
            $file->update(
                array_merge(
                    [
                        "path" => $path_to_store,
                        "mime_type" => $mime,
                    ],
                    $meta_info,
                )
            );
        } else {
            $file = $this->fileModel->create(
                array_merge(
                    [
                        "user_id" => $user_id,
                        "file_group" => $type,
                        "path" => $path_to_store,
                        "mime_type" => $mime,
                    ],
                    $meta_info,
                )
            );
        }

        return $file;
    }


    public function saveFromFile($file, $type, $file_id = null, $user_id = null): FileModel
    {
        $tmp_path = MethodsHelper::putFileInPrivateStorage($file, FileConstants::TMP_PATH);
        $path = storage_path("app/" . $tmp_path);
        return $this->save($path, $type, $file_id, $user_id);
    }

    public function saveFromFilePath($filepath, $type, $file_id = null, $user_id = null)
    {
        return $this->save($filepath, $type, $file_id, $user_id);
    }


    public static function cleanDelete($id, $delete = true)
    {
        $file = FileModel::find($id);
        if (!empty($file)) {
            MethodsHelper::deleteFileFromPrivateStorage($file->path);
            if ($delete) {
                $file->delete();
            }
        }
    }
}
