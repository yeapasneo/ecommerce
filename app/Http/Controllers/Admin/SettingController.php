<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiClient;
use App\Ai\AiException;
use App\Couriers\CourierManager;
use App\Http\Controllers\Controller;
use App\Messaging\MailConfig;
use App\Messaging\OrderNotifier;
use App\Messaging\Sms\SmsException;
use App\Messaging\Sms\SmsManager;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Setting;
use App\Payments\PaymentManager;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit(PaymentManager $payments, CourierManager $couriers)
    {
        // Webhook tokens are generated once; the admin pastes them into the courier panels.
        $generated = collect(['steadfast_webhook_token', 'pathao_webhook_secret'])
            ->filter(fn ($k) => blank(Setting::get($k)))
            ->mapWithKeys(fn ($k) => [$k => Str::random(40)]);
        if ($generated->isNotEmpty()) {
            Setting::put($generated->all());
        }
        $all = Setting::all_settings();

        return Inertia::render('Admin/Settings', [
            'values' => array_diff_key($all, array_flip(Setting::SECRETS)) + array_fill_keys(Setting::SECRETS, ''),
            'secretsSet' => collect(Setting::SECRETS)->mapWithKeys(fn ($k) => [$k => filled($all[$k] ?? null)]),
            'gateways' => collect(PaymentManager::GATEWAYS)->keys()->mapWithKeys(fn ($k) => [$k => [
                'configured' => $payments->driver($k)->isConfigured(),
                'live' => $payments->driver($k)->isEnabled(),
            ]]),
            'urls' => [
                'sitemap' => route('sitemap'),
                'robots' => route('robots'),
                'sslcommerzIpn' => route('payment.ipn', ['gateway' => 'sslcommerz']),
                'steadfastWebhook' => route('webhooks.courier', ['courier' => 'steadfast']),
                'pathaoWebhook' => route('webhooks.courier', ['courier' => 'pathao']),
            ],
            'couriers' => collect(CourierManager::COURIERS)->keys()->mapWithKeys(fn ($k) => [$k => [
                'configured' => $couriers->driver($k)->isConfigured(),
                'live' => $couriers->driver($k)->isEnabled(),
            ]]),
            'notifications' => [
                'events' => OrderNotifier::EVENTS,
                'smsProviders' => SmsManager::PROVIDERS,
                'smsReady' => app(SmsManager::class)->isConfigured(),
                'mailReady' => MailConfig::isConfigured(),
            ],
            'ogImageUrl' => $all['seo_og_image'] ? media_url($all['seo_og_image']) : null,
            'ai' => [
                'providers' => collect(AiClient::PROVIDERS)->map(fn ($p) => ['label' => $p['label'], 'key_url' => $p['key_url']]),
                'ready' => app(AiClient::class)->isConfigured(),
                'usedToday' => app(AiClient::class)->usedToday(),
            ],
        ]);
    }

    public function update(Request $request, PaymentManager $payments, CourierManager $couriers)
    {
        $flags = ['seo_noindex', 'cod_enabled', 'bkash_enabled', 'bkash_sandbox', 'nagad_enabled', 'nagad_sandbox', 'sslcommerz_enabled', 'sslcommerz_sandbox', 'eps_enabled', 'eps_sandbox',
            'courier_sync_status', 'steadfast_enabled', 'pathao_enabled', 'pathao_sandbox', 'checkout_otp', 'emi_enabled', 'abandoned_cart_enabled', 'ai_chat_enabled', 'ai_writer_auto',
            ...collect(OrderNotifier::EVENTS)->keys()->flatMap(fn ($e) => ["notify_sms_{$e}", "notify_email_{$e}"])->all()];
        $rules = collect(Setting::DEFAULTS)->map(fn () => ['nullable', 'string', 'max:1000'])->all();
        foreach (['delivery_charge_inside', 'delivery_charge_outside', 'express_delivery_charge', 'free_delivery_over'] as $k) {
            $rules[$k] = ['required', 'numeric', 'min:0'];
        }
        foreach ($flags as $k) {
            $rules[$k] = ['nullable', 'boolean'];
        }
        $rules['site_name'] = ['required', 'string', 'max:100'];
        $rules['seo_title'] = ['nullable', 'string', 'max:70'];
        $rules['seo_description'] = ['nullable', 'string', 'max:300'];
        // Strict formats: these IDs are printed inside <script> tags.
        $rules['gtm_container_id'] = ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/'];
        $rules['ga_measurement_id'] = ['nullable', 'regex:/^G-[A-Z0-9]{4,15}$/'];
        $rules['facebook_pixel_id'] = ['nullable', 'regex:/^\d{8,20}$/'];
        $rules['nagad_public_key'] = ['nullable', 'string', 'max:5000'];
        $rules['nagad_private_key'] = ['nullable', 'string', 'max:5000'];
        $rules['seo_og_image'] = ['nullable', 'image', 'max:2048'];
        $rules['pathao_delivery_type'] = ['nullable', 'in:48,12'];
        $rules['emi_min_amount'] = ['nullable', 'numeric', 'min:0'];
        $rules['emi_plans'] = ['nullable', 'string', 'max:200', 'regex:/^\s*\d+\s*:\s*\d+(\.\d+)?\s*(,\s*\d+\s*:\s*\d+(\.\d+)?\s*)*$/'];
        $rules['default_locale'] = ['nullable', 'in:en,bn'];
        $rules['abandoned_cart_delay_hours'] = ['nullable', 'integer', 'between:1,72'];
        $rules['vat_percent'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        $rules['sms_provider'] = ['nullable', Rule::in(array_keys(SmsManager::PROVIDERS))];
        $rules['notify_admin_email'] = ['nullable', 'email', 'max:255'];
        $rules['mail_port'] = ['nullable', 'integer', 'between:1,65535'];
        $rules['mail_encryption'] = ['nullable', 'in:tls,ssl,none'];
        $rules['mail_from_address'] = ['nullable', 'email', 'max:255'];
        $rules['fraud_provider'] = ['nullable', 'in:bdcourier'];
        $rules['fraud_block_mode'] = ['nullable', 'in:cod,all'];
        foreach (array_keys(OrderNotifier::EVENTS) as $e) {
            $rules["sms_template_{$e}"] = ['nullable', 'string', 'max:500'];
        }
        $rules['sms_template_otp'] = ['nullable', 'string', 'max:300', 'regex:/\{code\}/'];
        $rules['pathao_item_weight'] = ['nullable', 'numeric', 'min:0.5', 'max:10'];
        $rules['pathao_store_id'] = ['nullable', 'integer'];
        $rules['steadfast_webhook_token'] = ['nullable', 'string', 'min:16', 'max:100'];
        $rules['pathao_webhook_secret'] = ['nullable', 'string', 'min:16', 'max:100'];
        $rules['seo_og_image_remove'] = ['nullable', 'boolean'];
        $rules['ai_provider'] = ['nullable', Rule::in(array_keys(AiClient::PROVIDERS))];
        $rules['ai_fallback'] = ['nullable', Rule::in(['', ...array_keys(AiClient::PROVIDERS)])];
        foreach (array_keys(AiClient::PROVIDERS) as $p) {
            $rules["ai_{$p}_model"] = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/-]+$/'];
        }
        $rules['ai_daily_limit'] = ['nullable', 'integer', 'min:0', 'max:100000'];
        $rules['ai_writer_per_hour'] = ['nullable', 'integer', 'between:1,200'];
        $rules['ai_assistant_name'] = ['nullable', 'string', 'max:60'];
        $rules['ai_chat_greeting'] = ['nullable', 'string', 'max:300'];

        $data = $request->validate($rules, [
            'gtm_container_id.regex' => 'Use the container ID, e.g. GTM-ABC1234.',
            'ga_measurement_id.regex' => 'Use the GA4 measurement ID, e.g. G-ABC123XYZ.',
            'facebook_pixel_id.regex' => 'The Pixel ID is a number, e.g. 123456789012345.',
            'sms_template_otp.regex' => 'The code message must include {code}.',
            'emi_plans.regex' => 'Use months:interest pairs separated by commas, e.g. 3:0, 6:0, 12:7.',
        ]);

        foreach ($flags as $k) {
            $data[$k] = $request->boolean($k) ? '1' : '0';
        }
        // A blank secret field means "keep the saved value".
        foreach (Setting::SECRETS as $k) {
            if (blank($data[$k] ?? null)) {
                unset($data[$k]);
            }
        }
        unset($data['seo_og_image'], $data['seo_og_image_remove']);
        if ($request->hasFile('seo_og_image')) {
            $data['seo_og_image'] = $request->file('seo_og_image')->store('seo', 'public');
        } elseif ($request->boolean('seo_og_image_remove')) {
            $data['seo_og_image'] = '';
        }
        foreach (['gtm_container_id', 'ga_measurement_id', 'facebook_pixel_id', 'google_site_verification', 'bing_site_verification', 'facebook_domain_verification'] as $k) {
            $data[$k] = trim((string) ($data[$k] ?? ''));
        }

        $before = Setting::all_settings();
        Setting::put($data);
        $changed = collect($data)->filter(fn ($v, $k) => (string) ($before[$k] ?? '') !== (string) $v);
        if ($changed->isNotEmpty()) {
            Activity::log('updated', null, 'Changed settings: '.$changed->keys()->map(fn ($k) => str_replace('_', ' ', $k))->implode(', '),
                $changed->mapWithKeys(fn ($v, $k) => [$k => in_array($k, Setting::SECRETS, true) ? ['•••', '••• (changed)'] : [$before[$k] ?? null, $v]])->all());
        }

        // A gateway or courier can only be switched on once all of its credentials are saved.
        $incomplete = collect(PaymentManager::GATEWAYS)->keys()
            ->filter(fn ($k) => Setting::enabled("{$k}_enabled") && ! $payments->driver($k)->isConfigured())
            ->merge(collect(CourierManager::COURIERS)->keys()->filter(fn ($k) => Setting::enabled("{$k}_enabled") && ! $couriers->driver($k)->isConfigured()));
        if ($incomplete->isNotEmpty()) {
            Setting::put($incomplete->mapWithKeys(fn ($k) => ["{$k}_enabled" => '0'])->all());

            return back()->withErrors($incomplete->mapWithKeys(fn ($k) => ["{$k}_enabled" => 'Fill in all '.(Order::PAYMENT_METHODS[$k] ?? ucfirst($k)).' credentials before turning it on.'])->all())
                ->with('error', 'Settings saved, but some gateways or couriers were left off because their credentials are incomplete.');
        }

        return back()->with('success', 'Settings saved.');
    }

    public function testSms(Request $request, SmsManager $sms)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        try {
            $sms->send($data['phone'], 'Test message from '.Setting::get('site_name').'. SMS is working.');
        } catch (SmsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Sent. Check the phone.']);
    }

    public function testAi(Request $request, AiClient $ai)
    {
        $data = $request->validate(['provider' => ['required', Rule::in(array_keys(AiClient::PROVIDERS))]]);
        if (blank(Setting::get("ai_{$data['provider']}_key"))) {
            return response()->json(['ok' => false, 'message' => 'Save the API key first.'], 422);
        }
        try {
            $reply = $ai->test($data['provider']);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Connected. The AI replied: '.mb_substr($reply, 0, 60)]);
    }

    public function testEmail(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        try {
            MailConfig::apply();
            Mail::raw('Test email from '.Setting::get('site_name').'. Email is working.', fn ($m) => $m->to($data['email'])->subject('Test email'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not send: '.$e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => in_array(config('mail.default'), ['log', 'array'], true)
            ? 'No mail server is set, so the email was only written to the log. Fill in the SMTP details.'
            : 'Sent. Check the inbox (and spam folder).']);
    }

    public function messages()
    {
        return Inertia::render('Admin/Messages', [
            'messages' => ContactMessage::latest()->paginate($this->perPage())->withQueryString(),
            'subscribers' => NewsletterSubscriber::latest()->take(200)->get(),
        ]);
    }

    /** Email a reply to a contact-form message. */
    public function replyMessage(Request $request, ContactMessage $message)
    {
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000']]);
        if (! $message->email) {
            return back()->with('error', 'This message has no email address. Call the customer instead.');
        }
        try {
            MailConfig::apply();
            Mail::raw($data['reply'], fn ($m) => $m->to($message->email, $message->name)->subject('Re: '.$message->subject));
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not send the email: '.$e->getMessage());
        }
        $message->update(['reply' => $data['reply'], 'replied_at' => now(), 'replied_by' => $request->user()->id, 'is_read' => true]);

        return back()->with('success', in_array(config('mail.default'), ['log', 'array'], true)
            ? 'Saved, but no mail server is set up, so the email was only written to the log (Settings → Notifications).'
            : "Reply emailed to {$message->email}.");
    }

    public function readMessage(ContactMessage $message)
    {
        $message->update(['is_read' => true]);

        return back();
    }

    public function destroyMessage(ContactMessage $message)
    {
        $message->delete();

        return back()->with('success', 'Message deleted.');
    }
}
