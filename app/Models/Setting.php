<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        // Store
        'site_name' => 'TechNest',
        'tagline' => 'Your trusted computer & gadget shop',
        'phone' => '16793',
        'email' => 'support@technest.test',
        'address' => 'House 12, Road 4, Dhanmondi, Dhaka 1205',
        'delivery_charge_inside' => '60',
        'delivery_charge_outside' => '120',
        'express_delivery_charge' => '200',
        'free_delivery_over' => '50000',
        'facebook' => '',
        'youtube' => '',
        'instagram' => '',
        'notice' => 'Free home delivery on orders above ৳50,000. Visit our showrooms for the best deals!',

        // SEO
        'seo_title' => '',
        'seo_description' => 'Shop laptops, desktop PCs, components, monitors, phones and gadgets at the best prices in Bangladesh. Official warranty and fast home delivery.',
        'seo_keywords' => '',
        'seo_og_image' => '',
        'seo_noindex' => '0',
        'google_site_verification' => '',
        'bing_site_verification' => '',
        'facebook_domain_verification' => '',

        // Tracking
        'facebook_pixel_id' => '',
        'gtm_container_id' => '',
        'ga_measurement_id' => '',

        // Payments
        'cod_enabled' => '1',
        'bkash_enabled' => '0',
        'bkash_sandbox' => '1',
        'bkash_app_key' => '',
        'bkash_app_secret' => '',
        'bkash_username' => '',
        'bkash_password' => '',
        'nagad_enabled' => '0',
        'nagad_sandbox' => '1',
        'nagad_merchant_id' => '',
        'nagad_merchant_number' => '',
        'nagad_public_key' => '',
        'nagad_private_key' => '',
        'sslcommerz_enabled' => '0',
        'sslcommerz_sandbox' => '1',
        'sslcommerz_store_id' => '',
        'sslcommerz_store_password' => '',
        'eps_enabled' => '0',
        'eps_sandbox' => '1',
        'eps_merchant_id' => '',
        'eps_store_id' => '',
        'eps_username' => '',
        'eps_password' => '',
        'eps_hash_key' => '',
    ];

    /** Stored encrypted and never sent to the browser (the admin form only sees whether they are set). */
    public const SECRETS = [
        'bkash_app_secret', 'bkash_password',
        'nagad_private_key',
        'sslcommerz_store_password',
        'eps_password', 'eps_hash_key',
    ];

    /** The only settings shared with every page (storefront and admin). */
    public const PUBLIC = [
        'site_name', 'tagline', 'phone', 'email', 'address', 'notice',
        'delivery_charge_inside', 'delivery_charge_outside', 'express_delivery_charge', 'free_delivery_over',
        'facebook', 'youtube', 'instagram',
    ];

    public static function all_settings(): array
    {
        return Cache::rememberForever('settings', function () {
            return array_merge(self::DEFAULTS, static::pluck('value', 'key')->all());
        });
    }

    public static function public(): array
    {
        return array_intersect_key(self::all_settings(), array_flip(self::PUBLIC));
    }

    public static function get(string $key, $default = null)
    {
        $value = self::all_settings()[$key] ?? $default;

        return in_array($key, self::SECRETS, true) ? self::decrypt($value) : $value;
    }

    public static function enabled(string $key): bool
    {
        return (string) self::get($key) === '1';
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRETS, true) && $value !== null && $value !== '') {
                $value = Crypt::encryptString((string) $value);
            }
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings');
    }

    protected static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null; // APP_KEY changed or a value was written by hand
        }
    }
}
