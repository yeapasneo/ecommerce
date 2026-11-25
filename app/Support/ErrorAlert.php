<?php

namespace App\Support;

use App\Messaging\MailConfig;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells the shop owner when the live site hits an error: by email and/or a chat
 * webhook (Slack, Discord, Google Chat, Microsoft Teams…). The same error is
 * reported at most once an hour, and at most 20 alerts are sent per hour.
 */
class ErrorAlert
{
    protected static bool $sending = false;

    public static function enabled(): bool
    {
        try {
            return Setting::enabled('error_alerts_enabled') && (filled(Setting::get('error_alert_email')) || filled(Setting::get('error_alert_webhook')));
        } catch (Throwable) {
            return false; // e.g. the database itself is down
        }
    }

    public static function report(Throwable $e): void
    {
        if (self::$sending || app()->runningUnitTests() || ! self::enabled()) {
            return;
        }
        self::send($e);
    }

    /** Returns false when the alert was skipped (repeat within the hour, or hourly cap). */
    public static function send(Throwable $e, bool $test = false): bool
    {
        self::$sending = true;
        try {
            $key = 'error-alert:'.md5(get_class($e).$e->getFile().$e->getLine());
            if (! $test && (! Cache::add($key, 1, 3600) || Cache::increment('error-alert:hourly:'.now()->format('YmdH')) > 20)) {
                return false;
            }
            $site = (string) Setting::get('site_name');
            $request = app()->runningInConsole() ? null : request();
            $text = ($test ? '[Test] ' : '')."Error on {$site}\n"
                .class_basename($e).': '.Str::limit($e->getMessage(), 500)."\n"
                .'At '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine()."\n"
                .($request ? "Page: {$request->method()} {$request->fullUrl()}\n" : 'From: '.(app()->runningInConsole() ? 'a scheduled or queued job' : 'unknown')."\n")
                .'Time: '.now()->format('j M Y, H:i').' · App version: '.config('app.env')."\n"
                .'Repeats of this error are not sent again for an hour. Details: storage/logs/laravel.log';

            if ($webhook = Setting::get('error_alert_webhook')) {
                // "text" works for Slack, Google Chat and Teams; "content" for Discord.
                Http::timeout(5)->post($webhook, ['text' => $text, 'content' => Str::limit($text, 1900)]);
            }
            if ($email = Setting::get('error_alert_email')) {
                MailConfig::configure();
                Mail::raw($text, fn ($m) => $m->to($email)->subject(($test ? '[Test] ' : '')."Error on {$site}: ".Str::limit(class_basename($e).': '.$e->getMessage(), 80)));
            }

            return true;
        } catch (Throwable) {
            return false; // never let the alert itself break anything
        } finally {
            self::$sending = false;
        }
    }
}
