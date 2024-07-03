<?php

namespace App\Providers;

use App\Channels\FirebaseChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
     /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(ChannelManager::class)->extend('firebase', function () {
            return new FirebaseChannel;
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
