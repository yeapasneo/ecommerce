<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CartService
{
    protected function query(): Builder
    {
        $q = CartItem::query();

        return auth()->check()
            ? $q->where('user_id', auth()->id())
            : $q->whereNull('user_id')->where('session_id', session()->getId());
    }

    public function items(): Collection
    {
        return $this->query()->with('product', 'variant')->orderBy('id')->get()
            ->filter(fn ($i) => $i->product && (! $i->variant_id || $i->variant));
    }

    public function count(): int
    {
        return (int) $this->query()->sum('quantity');
    }

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): void
    {
        $item = $this->query()->where('product_id', $productId)->where('variant_id', $variantId)->first();
        if ($item) {
            $item->update(['quantity' => min(99, $item->quantity + $quantity)]);

            return;
        }
        CartItem::create([
            'user_id' => auth()->id(),
            'session_id' => auth()->check() ? null : session()->getId(),
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => min(99, max(1, $quantity)),
        ]);
    }

    public function update(int $itemId, int $quantity): void
    {
        $item = $this->query()->whereKey($itemId)->firstOrFail();
        $quantity <= 0 ? $item->delete() : $item->update(['quantity' => min(99, $quantity)]);
    }

    public function remove(int $itemId): void
    {
        $this->query()->whereKey($itemId)->delete();
    }

    public function clear(): void
    {
        $this->query()->delete();
    }

    /** Move a guest cart into the user's cart after login/registration. */
    public function mergeGuestCart(string $oldSessionId, int $userId): void
    {
        $guestItems = CartItem::whereNull('user_id')->where('session_id', $oldSessionId)->get();
        foreach ($guestItems as $guest) {
            $existing = CartItem::where('user_id', $userId)->where('product_id', $guest->product_id)->where('variant_id', $guest->variant_id)->first();
            if ($existing) {
                $existing->update(['quantity' => min(99, $existing->quantity + $guest->quantity)]);
                $guest->delete();
            } else {
                $guest->update(['user_id' => $userId, 'session_id' => null]);
            }
        }
    }

    public function subtotal(?Collection $items = null): float
    {
        return (float) ($items ?? $this->items())->sum(fn ($i) => $i->unitPrice() * $i->quantity);
    }

    public static function deliveryCharge(string $method, string $city, float $subtotal): float
    {
        if ($method === 'store_pickup') {
            return 0;
        }
        if ($method === 'express') {
            return (float) Setting::get('express_delivery_charge', 200);
        }
        $freeOver = (float) Setting::get('free_delivery_over', 0);
        if ($freeOver > 0 && $subtotal >= $freeOver) {
            return 0;
        }

        return strcasecmp(trim($city), 'Dhaka') === 0
            ? (float) Setting::get('delivery_charge_inside', 60)
            : (float) Setting::get('delivery_charge_outside', 120);
    }

    public function appliedCoupon(float $subtotal): ?Coupon
    {
        $code = session('coupon');
        if (! $code) {
            return null;
        }
        $coupon = Coupon::where('code', $code)->first();
        if (! $coupon || $coupon->validationError($subtotal)) {
            session()->forget('coupon');

            return null;
        }

        return $coupon;
    }

    public function summary(): array
    {
        $items = $this->items();
        $subtotal = $this->subtotal($items);
        $coupon = $this->appliedCoupon($subtotal);

        return [
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'quantity' => $i->quantity,
                'total' => $i->unitPrice() * $i->quantity,
                'variant' => $i->variant ? ['id' => $i->variant->id, 'name' => $i->variant->name] : null,
                'product' => array_merge($i->product->toCard(), [
                    'sku' => $i->variant?->sku ?: $i->product->sku,
                    'price' => $i->unitPrice(),
                    'image_url' => $i->variant?->image_url ?: $i->product->image_url,
                    'purchasable' => $i->variant ? $i->variant->isPurchasable() : $i->product->isPurchasable(),
                ]),
            ])->values(),
            'subtotal' => $subtotal,
            'coupon' => $coupon ? ['code' => $coupon->code, 'discount' => $coupon->discountFor($subtotal)] : null,
            'discount' => $coupon ? $coupon->discountFor($subtotal) : 0,
        ];
    }
}
