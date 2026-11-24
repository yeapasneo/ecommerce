<?php

namespace App\Providers;

use App\Support\Analytics;
use App\Support\Seo;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request (scoped also resets between Octane requests).
        $this->app->scoped(Seo::class);
        $this->app->scoped(Analytics::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
