<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Person;
use App\Models\Vendor;
use App\Services\Api\ApiJournal;
use App\Translation\DatabaseMergingLoader;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
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

        $this->app->scoped(ApiJournal::class);

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

        // Resolve the CP tenant before route-model binding, so /cp/clients/{id} of another tenant is a 404.
        // (Kept here rather than in bootstrap/app.php, which keeps being reverted on this machine.)
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \App\Http\Middleware\EnsureCpAuth::class,
            );
        }

        // Journal rows written by token-API requests (no-op unless the api.journal middleware started it).
        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($event) {
                $journal = $this->app->make(ApiJournal::class);
                if ($journal->active() && ($payload[0] ?? null) instanceof \Illuminate\Database\Eloquent\Model) {
                    $journal->capture($event, $payload[0]);
                }
            });
        }
    }
}
