<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
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
    ];

    public static function all_settings(): array
    {
        return Cache::rememberForever('settings', function () {
            return array_merge(self::DEFAULTS, static::pluck('value', 'key')->all());
        });
    }

    public static function get(string $key, $default = null)
    {
        return self::all_settings()[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings');
    }
}
