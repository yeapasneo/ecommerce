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
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
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
        // SESSION_DRIVER=auto: Redis when the server has it, otherwise the database.
        $autoCache = config('cache.default') === 'auto';
        $autoSession = config('session.driver') === 'auto';
        if ($autoCache || $autoSession) {
            $redis = CacheDriver::resolve(config('database.redis', []), CacheDriver::stateFile()) === 'redis';
            if ($autoCache) {
                config(['cache.default' => $redis ? 'redis_or_file' : 'file']);
            }
            if ($autoSession) {
                config(['session.driver' => $redis ? 'redis_or_database' : 'database']);
            }
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
        // Sessions in Redis that fall back to the database cache if Redis stops (SESSION_DRIVER=auto).
        Session::extend('redis_or_database', fn ($app) => new CacheBasedSessionHandler(
            clone $app['cache']->store('sessions_failover'), (int) config('session.lifetime'),
        ));

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
