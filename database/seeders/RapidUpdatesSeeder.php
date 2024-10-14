<?php

namespace Database\Seeders;

use App\Helpers\MethodsHelper;
use App\Models\PostCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RapidUpdatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PostCategory::whereNull("uuid")->update([
            "uuid" => MethodsHelper::getRandomToken(10)
        ]);
    }
}
