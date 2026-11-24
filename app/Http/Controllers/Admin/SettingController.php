<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Setting;
use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit(PaymentManager $payments)
    {
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
            ],
            'ogImageUrl' => $all['seo_og_image'] ? media_url($all['seo_og_image']) : null,
        ]);
    }

    public function update(Request $request, PaymentManager $payments)
    {
        $flags = ['seo_noindex', 'cod_enabled', 'bkash_enabled', 'bkash_sandbox', 'nagad_enabled', 'nagad_sandbox', 'sslcommerz_enabled', 'sslcommerz_sandbox', 'eps_enabled', 'eps_sandbox'];
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
        $rules['seo_og_image_remove'] = ['nullable', 'boolean'];

        $data = $request->validate($rules, [
            'gtm_container_id.regex' => 'Use the container ID, e.g. GTM-ABC1234.',
            'ga_measurement_id.regex' => 'Use the GA4 measurement ID, e.g. G-ABC123XYZ.',
            'facebook_pixel_id.regex' => 'The Pixel ID is a number, e.g. 123456789012345.',
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

        Setting::put($data);

        // A gateway can only be switched on once all of its credentials are saved.
        $incomplete = collect(PaymentManager::GATEWAYS)->keys()
            ->filter(fn ($k) => Setting::enabled("{$k}_enabled") && ! $payments->driver($k)->isConfigured());
        if ($incomplete->isNotEmpty()) {
            Setting::put($incomplete->mapWithKeys(fn ($k) => ["{$k}_enabled" => '0'])->all());

            return back()->withErrors($incomplete->mapWithKeys(fn ($k) => ["{$k}_enabled" => 'Fill in all '.Order::PAYMENT_METHODS[$k].' credentials before turning it on.'])->all())
                ->with('error', 'Settings saved, but some gateways were left off because their credentials are incomplete.');
        }

        return back()->with('success', 'Settings saved.');
    }

    public function messages()
    {
        return Inertia::render('Admin/Messages', [
            'messages' => ContactMessage::latest()->paginate($this->perPage())->withQueryString(),
            'subscribers' => NewsletterSubscriber::latest()->take(200)->get(),
        ]);
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
