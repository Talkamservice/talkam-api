<?php

namespace App\Http\Controllers\Auth;

use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function authenticated(Request $request, $user)
    {
        if (!AuthorizationService::checkForPermissions(["can_login_into_admin_dashboard"], $user) && !AuthorizationService::checkForRoles(["Sudo"], $user)) {
            auth()->logout();
            return back()->with(NotificationConstants::ERROR_MSG, "You do not have access. Kindly request for access and try again");
        }
    }
}
