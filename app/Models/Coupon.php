<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use LogsActivity;

    protected $fillable = ['code', 'type', 'value', 'min_order', 'max_discount', 'usage_limit', 'used', 'starts_at', 'expires_at', 'is_active'];

    protected $casts = [
        'value' => 'float',
        'min_order' => 'float',
        'max_discount' => 'float',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** Returns an error message, or null when the coupon can be applied to the subtotal. */
    public function validationError(float $subtotal): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is not active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not valid yet.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->usage_limit !== null && $this->used >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }
        if ($subtotal < $this->min_order) {
            return 'Minimum order of ৳'.number_format($this->min_order).' is required for this coupon.';
        }

        return null;
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent' ? round($subtotal * $this->value / 100, 2) : $this->value;
        if ($this->max_discount) {
            $discount = min($discount, $this->max_discount);
        }

        return min($discount, $subtotal);
    }
}
