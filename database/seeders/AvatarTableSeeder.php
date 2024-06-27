<?php

namespace Database\Seeders;

use App\Constants\General\StatusConstants;
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
        $random_files = File::files(public_path("samples/avatars"));
        foreach ($random_files as $key => $random_file) {
            try {
                $avatar_file = $random_file ?? fake()->randomElement($random_files);
                (new AvatarService)->create([
                    "name" => "Avatar " . $key,
                    "description" => "Avatar " . $key,
                    "avatar" => $this->createImage($avatar_file->getFilename(), $avatar_file->getPathname()),
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
