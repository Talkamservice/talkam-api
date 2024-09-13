<?php

namespace App\Providers;

use App\Channels\FirebaseChannel;
use App\Models\User;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(500);

        view()->composer('*', function ($view) {
            $view->with([
                'admin_assets' => url('/') . env('RESOURCE_PATH') . '/admin_assets',
            ]);
        });

        view()->composer([
            "dashboards.admin.layout.includes.header"
        ], function ($view) {
            $global_notifications = DatabaseNotification::where([
                "notifiable_type" => User::class,
                "notifiable_id" => auth()?->id()
            ])->get();
            $view->with([
                "global_notifications" => sudo()->notifications ?? $global_notifications
            ]);
        });

        Blade::if('canAll', function (...$permissions) {
            return auth()->user()->hasAllPermissions($permissions);
        });
    
        Blade::if('canAny', function (...$permissions) {
            return auth()->user()->hasAnyPermission($permissions);
        });
    }
}
