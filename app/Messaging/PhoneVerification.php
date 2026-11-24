<?php

namespace App\Messaging;

use App\Messaging\Sms\SmsManager;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** One-time SMS codes that confirm a customer's mobile number at checkout. */
class PhoneVerification
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(protected SmsManager $sms) {}

    public function required(): bool
    {
        return Setting::enabled('checkout_otp') && $this->sms->isConfigured();
    }

    public function isVerified(?string $phone): bool
    {
        return in_array(Phone::normalize($phone), session('verified_phones', []), true);
    }

    protected function key(string $phone): string
    {
        return 'otp:'.session()->getId().':'.Phone::normalize($phone);
    }

    public function send(string $phone): void
    {
        if (! Phone::isValid($phone)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number, e.g. 01712345678.']);
        }
        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($phone), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        $message = str_replace(['{code}', '{site}'], [$code, Setting::get('site_name')], (string) Setting::get('sms_template_otp'));
        $this->sms->send($phone, $message);
    }

    public function verify(string $phone, string $code): bool
    {
        $key = $this->key($phone);
        $entry = Cache::get($key);
        if (! $entry) {
            throw ValidationException::withMessages(['code' => 'The code has expired. Send a new one.']);
        }
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            throw ValidationException::withMessages(['code' => 'Too many wrong codes. Send a new one.']);
        }
        if (! Hash::check(trim($code), $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($key, $entry, now()->addMinutes(self::TTL_MINUTES));
            throw ValidationException::withMessages(['code' => 'That code is not correct.']);
        }

        Cache::forget($key);
        $verified = session('verified_phones', []);
        $verified[] = Phone::normalize($phone);
        session(['verified_phones' => array_values(array_unique($verified))]);

        return true;
    }
}
