<?php

namespace App\Http\Controllers\Web;

use App\Helpers\MethodsHelper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class IndexController extends Controller
{
    /** Read a file via url */
    public function readFile($path)
    {
        return MethodsHelper::getFileFromPrivateStorage(
            MethodsHelper::readFileUrl('decrypt', $path)
        );
    }

    /** Read a file via url */
    public function downloadsample($path)
    {
        return Storage::download(asset('samples/' . base64_decode($path)));
    }

    // download sample from cloudinary
    public function downloadSampleFromCloudinary(string $url)
    {
        // download sample from cloudinary
        return redirect()->away(base64_decode($url));
    }
}
