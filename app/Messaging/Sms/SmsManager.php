<?php

namespace App\Messaging\Sms;

use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Bangladeshi SMS gateways. Each needs an API key; most also need an approved
 * sender ID (masking) or, for SSL Wireless, the SID they issue.
 */
class SmsManager
{
    public const PROVIDERS = [
        'bulksmsbd' => 'BulkSMSBD',
        'alphasms' => 'Alpha SMS (sms.net.bd)',
        'sslwireless' => 'SSL Wireless',
    ];

    public function provider(): ?string
    {
        $p = Setting::get('sms_provider');

        return isset(self::PROVIDERS[$p]) ? $p : null;
    }

    public function isConfigured(): bool
    {
        $p = $this->provider();
        if (! $p || blank(Setting::get('sms_api_key'))) {
            return false;
        }

        // BulkSMSBD and SSL Wireless can't send without a sender ID / SID.
        return $p === 'alphasms' || filled(Setting::get('sms_sender_id'));
    }

    /** @throws SmsException */
    public function send(string $to, string $message): void
    {
        if (! $this->isConfigured()) {
            throw new SmsException('SMS is not set up.');
        }
        if (! Phone::isValid($to)) {
            throw new SmsException("{$to} is not a valid Bangladeshi mobile number.");
        }
        $key = (string) Setting::get('sms_api_key');
        $sender = (string) Setting::get('sms_sender_id');
        $http = Http::timeout(20)->acceptJson();

        try {
            match ($this->provider()) {
                'bulksmsbd' => $this->check(
                    $http->asForm()->post('https://bulksmsbd.net/api/smsapi', [
                        'api_key' => $key, 'type' => 'text', 'number' => Phone::international($to), 'senderid' => $sender, 'message' => $message,
                    ]),
                    fn ($r) => (int) $r->json('response_code') === 202,
                    fn ($r) => $r->json('error_message') ?? $r->json('success_message'),
                ),
                'alphasms' => $this->check(
                    $http->asForm()->post('https://api.sms.net.bd/sendsms', array_filter([
                        'api_key' => $key, 'msg' => $message, 'to' => Phone::international($to), 'sender_id' => $sender ?: null,
                    ])),
                    fn ($r) => $r->json('error') === 0 || $r->json('error') === '0',
                    fn ($r) => $r->json('msg'),
                ),
                'sslwireless' => $this->check(
                    $http->asJson()->post('https://smsplus.sslwireless.com/api/v3/send-sms', [
                        'api_token' => $key, 'sid' => $sender, 'msisdn' => Phone::international($to), 'sms' => $message,
                        'csms_id' => Str::upper(Str::random(16)),
                    ]),
                    fn ($r) => strtoupper((string) $r->json('status')) === 'SUCCESS',
                    fn ($r) => $r->json('error_message') ?? $r->json('status_message'),
                ),
            };
        } catch (SmsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SmsException('Could not reach the SMS gateway: '.$e->getMessage());
        }
    }

    protected function check($response, callable $ok, callable $error): void
    {
        if (! $response->successful() || ! $ok($response)) {
            throw new SmsException('The SMS gateway refused the message: '.($error($response) ?: 'HTTP '.$response->status()));
        }
    }
}
