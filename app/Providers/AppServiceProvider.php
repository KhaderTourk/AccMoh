<?php

namespace App\Providers;

use App\Translation\DatabaseMergingLoader;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $helpers = base_path('app/Helpers/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $this->app->extend('translation.loader', function ($loader) {
            return new DatabaseMergingLoader($loader);
        });
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
