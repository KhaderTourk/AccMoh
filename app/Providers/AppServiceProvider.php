<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Person;
use App\Models\Vendor;
use App\Translation\DatabaseMergingLoader;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        Relation::morphMap([
            'client' => Client::class,
            'person' => Person::class,
            'vendor' => Vendor::class,
        ]);
    }
}
