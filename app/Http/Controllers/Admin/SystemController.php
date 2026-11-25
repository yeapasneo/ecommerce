<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\BackupDatabase;
use App\Http\Controllers\Controller;
use App\Messaging\MailConfig;
use App\Messaging\Sms\SmsManager;
use App\Models\Setting;
use App\Models\User;
use App\Support\CacheDriver;
use App\Support\ErrorAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/** Go-live checklist: what is set up, what is broken, and how to fix it. */
class SystemController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/System', ['checks' => $this->checks(), 'backups' => $this->backups()]);
    }

    protected function checks(): array
    {
        $checks = [];
        $add = function (string $group, string $label, string $state, string $detail, ?string $fix = null) use (&$checks) {
            $checks[] = compact('group', 'label', 'state', 'detail', 'fix');
        };

        // Security
        $production = app()->environment('production');
        $add('Security', 'Production mode', $production && ! config('app.debug') ? 'ok' : 'fail',
            'APP_ENV='.app()->environment().', APP_DEBUG='.(config('app.debug') ? 'true' : 'false'),
            'Set APP_ENV=production and APP_DEBUG=false in .env. Debug mode shows code and passwords on error pages.');
        $add('Security', 'HTTPS', str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'fail',
            'APP_URL='.config('app.url'), 'Put an SSL certificate on the domain and set APP_URL=https://yourdomain.com.');
        $weak = User::where('is_admin', true)->get(['id', 'email', 'password'])->filter(fn ($u) => Hash::check('password', $u->password))->pluck('email');
        $add('Security', 'Admin passwords', $weak->isEmpty() ? 'ok' : 'fail',
            $weak->isEmpty() ? 'No admin uses the demo password.' : 'Still using the demo password: '.$weak->implode(', '),
            'Log in as that admin and change the password under My profile, or remove the demo admin.');

        // Background work
        $queue = config('queue.default');
        if ($queue === 'sync') {
            $add('Background jobs', 'Queue worker', 'warn', 'QUEUE_CONNECTION=sync: SMS and emails are sent while the customer waits.', 'Set QUEUE_CONNECTION=database and run a queue worker (see DEPLOY.md).');
        } else {
            $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(5)->timestamp)->count();
            $pending = DB::table('jobs')->count();
            $add('Background jobs', 'Queue worker', $stuck ? 'fail' : 'ok',
                $stuck ? "{$stuck} job(s) waiting over 5 minutes, so the worker is probably not running." : "{$pending} job(s) waiting.",
                'Start the worker with Supervisor: php artisan queue:work --tries=3 (see DEPLOY.md).');
        }
        $failed = DB::table('failed_jobs')->count();
        $add('Background jobs', 'Failed jobs', $failed ? 'warn' : 'ok', $failed ? "{$failed} job(s) failed. Retry them below once the cause is fixed." : 'None.');
        $beat = Cache::get('scheduler:last_run');
        $beatAge = $beat ? Carbon::parse($beat)->diffInMinutes(now()) : null;
        $add('Background jobs', 'Scheduler', $beat && $beatAge <= 3 ? 'ok' : 'fail',
            $beat ? 'Last run '.Carbon::parse($beat)->diffForHumans().'.' : 'Has never run.',
            'Add the cron entry: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');
        $latest = $this->backups()[0] ?? null;
        $add('Background jobs', 'Database backup', $latest && $latest['age_hours'] <= 26 ? 'ok' : ($latest ? 'warn' : 'fail'),
            $latest ? "Latest: {$latest['name']} ({$latest['size']}), {$latest['ago']}." : (Cache::get('backup:last_error')['error'] ?? 'No backup yet.'),
            'Backups run nightly at 02:30 through the scheduler. Copy them off the server regularly too.');

        // Messaging
        $add('Messaging', 'Email', MailConfig::isConfigured() ? 'ok' : 'warn',
            MailConfig::isConfigured() ? 'Mail server is set.' : 'Emails are only written to the log.', 'Fill in SMTP under Settings → Notifications.');
        $add('Messaging', 'SMS', app(SmsManager::class)->isConfigured() ? 'ok' : 'warn',
            app(SmsManager::class)->isConfigured() ? 'SMS gateway is set.' : 'No SMS gateway, so customers get no SMS updates or checkout codes.', 'Choose a provider under Settings → Notifications.');

        // Store setup
        $add('Store', 'Public files link', File::exists(public_path('storage')) ? 'ok' : 'fail',
            File::exists(public_path('storage')) ? 'public/storage is linked.' : 'Uploaded images will not show.', 'Run: php artisan storage:link');
        $sandbox = collect(['bkash', 'nagad', 'sslcommerz', 'eps', 'pathao'])
            ->filter(fn ($k) => Setting::enabled("{$k}_enabled") && Setting::enabled("{$k}_sandbox"))
            ->map(fn ($k) => ucfirst($k));
        $add('Store', 'Live payments & couriers', $sandbox->isEmpty() ? 'ok' : ($production ? 'fail' : 'warn'),
            $sandbox->isEmpty() ? 'No enabled gateway or courier is in sandbox mode.' : 'Still in sandbox (test) mode: '.$sandbox->implode(', ').'.',
            'Enter live credentials and turn sandbox mode off in Settings.');
        $add('Store', 'Search engines', Setting::enabled('seo_noindex') ? 'warn' : 'ok',
            Setting::enabled('seo_noindex') ? 'The site is hidden from search engines.' : 'Search engines can index the site.', 'Turn off "Hide the whole site" in Settings → SEO when you launch.');
        $store = config('cache.default');
        $redis = $store === 'redis_or_file' || config("cache.stores.{$store}.driver") === 'redis';
        $add('Store', 'Cache', $redis ? 'ok' : 'warn',
            $redis ? 'Using Redis (falls back to the file cache if Redis stops).' : 'Using the '.($store === 'file' ? 'file' : $store).' cache. Redis is '.(CacheDriver::hasClient() ? 'not running.' : 'not installed.'),
            'Install Redis and the PHP Redis extension (sudo apt install redis-server php-redis) and keep CACHE_STORE=auto. The shop switches to Redis within a minute.');
        $session = config('session.driver');
        $add('Store', 'Sessions', $session === 'redis_or_database' || $session === 'redis' ? 'ok' : 'warn',
            $session === 'redis_or_database' ? 'Stored in Redis (fall back to the database if Redis stops).' : "Stored in: {$session}.",
            'Install Redis (see Cache above) and keep SESSION_DRIVER=auto. Switching logs signed-in users out once.');
        $add('Store', 'Error alerts', ErrorAlert::enabled() ? 'ok' : ($production ? 'warn' : 'ok'),
            ErrorAlert::enabled() ? 'You get an email or chat message when the site has an error.' : 'Off: errors are only written to the log.',
            'Turn on Error Alerts in Settings → Notifications. For downtime, also add '.url('/up').' to a free uptime monitor (e.g. UptimeRobot).');
        $add('Store', 'Optimized config', app()->configurationIsCached() ? 'ok' : 'warn',
            app()->configurationIsCached() ? 'Config and routes are cached.' : 'Not cached (fine while developing).', 'Run php artisan optimize after each deploy.');

        return $checks;
    }

    protected function backups(): array
    {
        return collect(File::glob(BackupDatabase::directory().'/*.dump'))->sortDesc()->take(14)->map(fn ($f) => [
            'name' => basename($f),
            'size' => round(filesize($f) / 1048576, 1).' MB',
            'ago' => Carbon::createFromTimestamp(filemtime($f))->diffForHumans(),
            'age_hours' => (int) floor((time() - filemtime($f)) / 3600),
        ])->values()->all();
    }

    public function backup()
    {
        $code = Artisan::call('app:backup');

        return back()->with($code === 0 ? 'success' : 'error', trim(Artisan::output()));
    }

    public function download(string $name)
    {
        $path = BackupDatabase::directory().'/'.basename($name);
        abort_unless(str_ends_with($path, '.dump') && File::exists($path), 404);

        return response()->download($path);
    }

    public function retryFailed()
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', 'Failed jobs were queued again.');
    }
}
