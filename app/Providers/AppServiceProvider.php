<?php

namespace App\Providers;

use App\Messaging\MailConfig;
use App\Models\User;
use App\Support\Activity;
use App\Support\Analytics;
use App\Support\Seo;
use Illuminate\Auth\Events\Login;
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
        Event::listen(Login::class, function ($event) {
            if ($event->user->is_admin) {
                Activity::log('signed_in', null, 'Signed in to the admin panel');
            }
        });
    }
}
