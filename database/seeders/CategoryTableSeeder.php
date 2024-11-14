<?php

namespace Database\Seeders;

use App\Constants\General\StatusConstants;
use App\Models\PostCategory;
use Illuminate\Database\Seeder;

class CategoryTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                "name" => "Entertainments",
                "description" => "Explore and enjoy various forms of entertainment.",
                "image" => "https://images.pexels.com/photos/1059078/pexels-photo-1059078.jpeg", // Entertainment image
            ],
            [
                "name" => "Movies",
                "description" => "Find and watch the latest movies and classic films.",
                "image" => "https://images.pexels.com/photos/1117132/pexels-photo-1117132.jpeg", // Movies image
            ],
            [
                "name" => "Sports",
                "description" => "Stay updated with live sports events and news.",
                "image" => "https://images.pexels.com/photos/47730/the-ball-stadion-football-the-pitch-47730.jpeg", // Sports image
            ],
            [
                "name" => "Lifestyles",
                "description" => "Discover lifestyle tips and trends for better living.",
                "image" => "https://images.pexels.com/photos/934718/pexels-photo-934718.jpeg", // Lifestyles image
            ],
            [
                "name" => "Literature",
                "description" => "Browse and read a wide range of literary works.",
                "image" => "https://images.pexels.com/photos/46274/pexels-photo-46274.jpeg", // Literature image
            ],
        ];

        foreach ($data as $d) {
            $d["status"] = StatusConstants::ACTIVE;
            PostCategory::firstOrCreate([
                "name" => $d["name"]
            ], $d);
        }
    }
}
