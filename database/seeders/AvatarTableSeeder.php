<?php

namespace Database\Seeders;

use App\Constants\General\StatusConstants;
use App\Models\Avatar;
use App\Services\User\AvatarService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Support\Facades\File;


class AvatarTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $avatar_files = array_merge(
            File::files(public_path("samples/avatars")),
            File::files(public_path("samples/additional_avatar"))
        );

        foreach ($avatar_files as $key => $avatar_file) {
            try {
                $avatar_name = "Avatar " . $key;
                if (Avatar::where('name', $avatar_name)->exists()) {
                    continue;
                }

                (new AvatarService)->create([
                    "name" => $avatar_name,
                    "description" => "Avatar " . $key,
                    "image" => $this->createImage($avatar_file->getFilename(), $avatar_file->getPathname()),
                    "status" => StatusConstants::ACTIVE
                ]);
            } catch (\Throwable $th) {
                throw $th;
            }
        }
    }

    function createImage($name, $path)
    {
        return (new FileFactory)->createWithContent($name, file_get_contents($path));
    }
}
