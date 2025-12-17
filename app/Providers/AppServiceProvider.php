<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CloudinaryService;
use App\Services\SmsService;


class AppServiceProvider extends ServiceProvider
{



    /**
     * Register any application services.
     */

    public function register(): void
    {
        $this->app->singleton(CloudinaryService::class, function ($app) {
            return new CloudinaryService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
