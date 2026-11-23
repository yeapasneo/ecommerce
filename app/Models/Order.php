<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public const PAYMENT_STATUSES = ['unpaid', 'paid', 'refunded'];

    public const PAYMENT_METHODS = ['cod' => 'Cash on Delivery', 'bkash' => 'bKash', 'online' => 'Online Payment (Card)'];

    public const DELIVERY_METHODS = ['home_delivery' => 'Home Delivery', 'express' => 'Express Delivery', 'store_pickup' => 'Store Pickup'];

    protected $fillable = [
        'order_number', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'notes',
        'delivery_method', 'payment_method', 'payment_status', 'status', 'subtotal', 'delivery_charge', 'discount', 'total', 'coupon_code',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'delivery_charge' => 'float',
        'discount' => 'float',
        'total' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public static function generateNumber(): string
    {
        do {
            $number = now()->format('ymd').random_int(10000, 99999);
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
