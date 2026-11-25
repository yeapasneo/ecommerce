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

        // Couriers
        'courier_sync_status' => '1',
        'steadfast_enabled' => '0',
        'steadfast_api_key' => '',
        'steadfast_secret_key' => '',
        'steadfast_webhook_token' => '',
        'pathao_enabled' => '0',
        'pathao_sandbox' => '1',
        'pathao_client_id' => '',
        'pathao_client_secret' => '',
        'pathao_username' => '',
        'pathao_password' => '',
        'pathao_store_id' => '',
        'pathao_delivery_type' => '48',
        'pathao_item_weight' => '0.5',
        'pathao_webhook_secret' => '',

        'vat_percent' => '0',            // default VAT on staff orders
        'emi_enabled' => '0',
        'emi_min_amount' => '5000',
        'emi_plans' => '3:0, 6:0, 9:5, 12:7',
        'emi_note' => '0% EMI on selected bank credit cards. Pay by card at checkout and ask your bank to convert it to EMI.',
        'default_locale' => 'en',        // en | bn
        'abandoned_cart_enabled' => '0',

        // Returns and warranty
        'returns_enabled' => '1',
        'return_days' => '7',
        'notify_returns' => '1',

        // Product alerts
        'back_in_stock_enabled' => '1',
        'price_drop_alerts' => '1',
        'sms_template_back_in_stock' => '{product} is back in stock at {site}. Order now: {link}',
        'sms_template_price_drop' => 'Hi {name}, {product} on your wishlist is now Tk {price} (was Tk {old}) at {site}: {link}',

        // Loyalty points and referrals
        'loyalty_enabled' => '0',
        'loyalty_earn_per' => '100', // spend this many Taka to earn 1 point
        'loyalty_point_value' => '1', // 1 point = this many Taka off
        'loyalty_min_redeem' => '100',
        'loyalty_max_percent' => '20', // points can pay at most this share of the items
        'referral_enabled' => '0',
        'referral_bonus' => '200', // points for the person who invited
        'referral_welcome' => '100', // points for the new customer

        // Inventory
        'low_stock_threshold' => '3',
        'low_stock_alerts' => '0',
        'low_stock_email' => '',

        // AI (Settings → AI). Keys are entered later; everything stays off until then.
        'ai_provider' => 'gemini',
        'ai_fallback' => 'groq',
        'ai_gemini_key' => '',
        'ai_gemini_model' => 'gemini-3.5-flash-lite, gemini-3.6-flash, gemini-3.1-flash-lite',
        'ai_groq_key' => '',
        'ai_groq_model' => 'openai/gpt-oss-120b',
        'ai_openrouter_key' => '',
        'ai_openrouter_model' => 'meta-llama/llama-3.3-70b-instruct:free',
        'ai_daily_limit' => '500',
        'ai_chat_enabled' => '0',
        'ai_assistant_name' => '',
        'ai_chat_greeting' => 'Hi! Looking for something? Tell me what you need and your budget, and I will suggest products from our shop. You can also ask about an order.',
        'ai_writer_auto' => '0',
        'ai_writer_per_hour' => '10',
        'abandoned_cart_delay_hours' => '2',
        'sms_template_abandoned_cart' => 'Hi {name}, you left items worth Tk {total} in your cart at {site}. Complete your order: {link}',

        // Notifications
        'sms_provider' => '',            // bulksmsbd | alphasms | sslwireless
        'sms_api_key' => '',
        'sms_sender_id' => '',
        'notify_admin_email' => '',      // new-order alerts for the shop
        'checkout_otp' => '0',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => '',
        'notify_sms_order_placed' => '1',
        'notify_sms_order_confirmed' => '0',
        'notify_sms_order_shipped' => '1',
        'notify_sms_order_delivered' => '1',
        'notify_sms_order_cancelled' => '1',
        'notify_sms_payment_received' => '1',
        'notify_email_order_placed' => '1',
        'notify_email_order_confirmed' => '1',
        'notify_email_order_shipped' => '1',
        'notify_email_order_delivered' => '1',
        'notify_email_order_cancelled' => '1',
        'notify_email_payment_received' => '1',
        'sms_template_order_placed' => 'Dear {name}, your order #{order} of Tk {total} is placed at {site}. We will call you to confirm.',
        'sms_template_order_confirmed' => 'Your order #{order} is confirmed and being packed. - {site}',
        'sms_template_order_shipped' => 'Your order #{order} is on the way{courier}. Track: {tracking_url} - {site}',
        'sms_template_order_delivered' => 'Your order #{order} has been delivered. Thank you for shopping with {site}!',
        'sms_template_order_cancelled' => 'Your order #{order} has been cancelled. Questions? Call {phone}. - {site}',
        'sms_template_payment_received' => 'We received Tk {amount} for order #{order}. Thank you! - {site}',
        'sms_template_otp' => '{code} is your {site} verification code. It expires in 10 minutes.',

        // Fraud check
        'fraud_provider' => '',          // bdcourier
        'bdcourier_api_key' => '',
        'fraud_block_mode' => 'cod',     // cod | all
    ];

    /** Stored encrypted and never sent to the browser (the admin form only sees whether they are set). */
    public const SECRETS = [
        'bkash_app_secret', 'bkash_password',
        'nagad_private_key',
        'sslcommerz_store_password',
        'eps_password', 'eps_hash_key',
        'steadfast_api_key', 'steadfast_secret_key',
        'pathao_client_secret', 'pathao_password',
        'sms_api_key', 'mail_password', 'bdcourier_api_key',
        'ai_gemini_key', 'ai_groq_key', 'ai_openrouter_key',
    ];

    /** The only settings shared with every page (storefront and admin). */
    public const PUBLIC = [
        'site_name', 'tagline', 'phone', 'email', 'address', 'notice',
        'delivery_charge_inside', 'delivery_charge_outside', 'express_delivery_charge', 'free_delivery_over',
        'facebook', 'youtube', 'instagram',
        'loyalty_enabled', 'loyalty_earn_per', 'loyalty_point_value', 'referral_enabled', 'referral_welcome',
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
