<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\Therapist;
use App\Models\User;
use App\Models\WellnessCourse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $data = [
            "cards" => [
                [
                    "icon" => "users",
                    "title" => "Total Users",
                    "value" => User::where("role", UserConstants::USER)->count(),
                    "class" => "primary",
                    "url" => route("admin.users.index")
                ],
                [
                    "icon" => "books",
                    "title" => "Total Categories",
                    "value" => 0,
                    "class" => "info",
                    "url" => ""
                ],
                [
                    "icon" => "books",
                    "title" => "Total Posts",
                    "value" => 0,
                    "class" => "warning",
                    "url" => ""
                ],
                [
                    "icon" => "books",
                    "title" => "Total Groups",
                    "value" =>  0,
                    "class" => "primary",
                    "url" => ""
                ]
            ],
            "users" => User::latest()->limit(5)->get(),
        ];

        return view("dashboards.admin.pages.index", $data);
    }
}
