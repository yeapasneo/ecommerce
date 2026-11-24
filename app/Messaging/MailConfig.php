<?php

namespace App\Messaging;

use App\Models\Setting;

/** Applies the SMTP details saved in Settings → Notifications (otherwise the .env MAIL_* values are used). */
class MailConfig
{
    public static function apply(): void
    {
        if ($host = Setting::get('mail_host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.scheme' => Setting::get('mail_encryption') === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) (Setting::get('mail_port') ?: 587),
                'mail.mailers.smtp.username' => Setting::get('mail_username'),
                'mail.mailers.smtp.password' => Setting::get('mail_password'),
            ]);
        }
        if ($from = Setting::get('mail_from_address')) {
            config(['mail.from.address' => $from]);
        }
        config(['mail.from.name' => Setting::get('mail_from_name') ?: Setting::get('site_name', config('app.name'))]);
        app('mail.manager')->forgetMailers();
    }

    public static function isConfigured(): bool
    {
        return filled(Setting::get('mail_host')) || ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
