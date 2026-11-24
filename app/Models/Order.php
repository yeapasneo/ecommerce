<?php

namespace App\Models;

use App\Couriers\CourierManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public const PAYMENT_STATUSES = ['unpaid', 'paid', 'refunded'];

    /** Every payment method an order can carry ('online' is kept for older orders). */
    public const PAYMENT_METHODS = [
        'cod' => 'Cash on Delivery',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'sslcommerz' => 'Card / Mobile Banking (SSLCommerz)',
        'eps' => 'EPS',
        'online' => 'Online Payment',
    ];

    public const DELIVERY_METHODS = ['home_delivery' => 'Home Delivery', 'express' => 'Express Delivery', 'store_pickup' => 'Store Pickup'];

    protected $fillable = [
        'order_number', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'notes',
        'delivery_method', 'payment_method', 'payment_status', 'status', 'subtotal', 'delivery_charge', 'discount', 'total', 'coupon_code',
        'gateway_ref', 'transaction_id', 'paid_at',
        'courier', 'courier_consignment_id', 'courier_tracking_code', 'courier_status', 'courier_fee', 'courier_cod', 'courier_sent_at', 'courier_status_at',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'delivery_charge' => 'float',
        'discount' => 'float',
        'total' => 'float',
        'paid_at' => 'datetime',
        'courier_fee' => 'float',
        'courier_cod' => 'float',
        'courier_sent_at' => 'datetime',
        'courier_status_at' => 'datetime',
    ];

    protected $appends = ['courier_tracking_url'];

    public function getCourierTrackingUrlAttribute(): ?string
    {
        return $this->courier ? app(CourierManager::class)->trackingUrl($this) : null;
    }

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

    /** Record a verified gateway payment. Safe to call more than once (callback + IPN). */
    public function markPaid(string $gateway, ?string $transactionId, ?string $reference = null): bool
    {
        return DB::transaction(function () use ($gateway, $transactionId, $reference) {
            $order = static::lockForUpdate()->find($this->id);
            if ($order->payment_status === 'paid') {
                return false;
            }
            $order->update([
                'payment_status' => 'paid',
                'payment_method' => $gateway,
                'transaction_id' => $transactionId,
                'gateway_ref' => $reference ?? $order->gateway_ref,
                'paid_at' => now(),
            ]);
            $order->histories()->create([
                'status' => $order->status,
                'comment' => 'Payment received via '.(self::PAYMENT_METHODS[$gateway] ?? $gateway).($transactionId ? " (TrxID {$transactionId})" : '').'.',
            ]);
            $this->refresh();

            return true;
        });
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public static function generateNumber(): string
    {
        do {
            $number = now()->format('ymd').random_int(10000, 99999);
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
