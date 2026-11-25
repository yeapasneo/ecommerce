<?php

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Runs from one cron entry on the server:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/

// Lets Admin → System Status confirm the scheduler is running.
Schedule::call(fn () => Cache::forever('scheduler:last_run', now()->toIso8601String()))->everyMinute()->name('scheduler-heartbeat');

// Catch courier updates whose webhook never arrived.
Schedule::command('couriers:sync')->everyThirtyMinutes()->withoutOverlapping()->runInBackground();

// One reminder for checkouts left unfinished (Settings → Notifications).
Schedule::command('carts:remind')->hourly()->withoutOverlapping();

// AI-written product texts for staff to review (only when turned on in Settings → AI).
Schedule::command('ai:write-drafts')->hourly()->withoutOverlapping();

// Nightly database backup (keeps 14).
Schedule::command('app:backup')->dailyAt('02:30')->withoutOverlapping();

// Housekeeping.
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::call(fn () => NotificationLog::where('created_at', '<', now()->subMonths(6))->delete())->weekly()->name('prune-notification-log');
