<?php

namespace App\Providers;

use App\Messaging\MailConfig;
use App\Models\User;
use App\Support\Activity;
use App\Support\Analytics;
use App\Support\CacheDriver;
use App\Support\Seo;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // CACHE_STORE=auto: Redis when the server has it, otherwise the file cache.
        if (config('cache.default') === 'auto') {
            $store = CacheDriver::resolve(config('database.redis', []), CacheDriver::stateFile());
            config(['cache.default' => $store === 'redis' ? 'redis_or_file' : 'file']);
        }

        // Every email (order updates, password resets…) uses the SMTP details saved in Settings.
        $this->app->afterResolving('mail.manager', function () {
            try {
                MailConfig::configure();
            } catch (\Throwable) {
                // database not ready yet (fresh install, migrations): keep the .env mail settings
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (array_keys(User::PERMISSIONS) as $permission) {
            Gate::define("admin.{$permission}", fn (User $user) => $user->hasPermission($permission));
        }
        // Redis stopped answering: re-check on the next request instead of trusting the old result.
        Event::listen(CacheFailedOver::class, fn () => CacheDriver::forget(CacheDriver::stateFile()));

        Event::listen(Login::class, function ($event) {
            if ($event->user->is_admin) {
                Activity::log('signed_in', null, 'Signed in to the admin panel');
            }
        });
    }
}
