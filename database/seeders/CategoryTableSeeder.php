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
                "name" => "Caterers",
                "description" => "Browse and book chefs for your events",
                "image" => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDWj9w21gJI5mcQ4yTwg6cG1fgI9YGmMqSKA&usqp=CAU",
            ],
            [
                "name" => "Djs",
                "description" => "Browse and book DJs in your neighbourhood",
                "image" => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDWj9w21gJI5mcQ4yTwg6cG1fgI9YGmMqSKA&usqp=CAU",
            ],
            [
                "name" => "Event planners",
                "description" => "Browse and book event planners for your events",
                "image" => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDWj9w21gJI5mcQ4yTwg6cG1fgI9YGmMqSKA&usqp=CAU",
            ],
            [
                "name" => "Hair Stylists",
                "description" => "Browse and book hair stylists in your neighbourhood",
                "image" => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDWj9w21gJI5mcQ4yTwg6cG1fgI9YGmMqSKA&usqp=CAU",
            ],
            [
                "name" => "Photographers",
                "description" => "Browse and book photographers in your neighbourhood",
                "image" => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDWj9w21gJI5mcQ4yTwg6cG1fgI9YGmMqSKA&usqp=CAU",
            ],
        ];

        foreach ($data as $d) {
            $d["status"] = StatusConstants::ACTIVE;
            PostCategory::create($d);
        }
    }
}
