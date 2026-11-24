<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;

/**
 * E-commerce events for GA4 / Tag Manager / Meta Pixel. Events are queued in the
 * session and handed to the browser on the next rendered page, so events raised
 * before a redirect (e.g. add to cart) are not lost.
 */
class Analytics
{
    public function push(string $event, array $params): static
    {
        session()->push('analytics_queue', ['event' => $event, 'params' => $params]);

        return $this;
    }

    /** Pending events; reading them clears the queue. */
    public function pull(): array
    {
        return session()->pull('analytics_queue', []);
    }

    public static function item(Product $product, int $quantity = 1, ?float $price = null): array
    {
        return array_filter([
            'item_id' => $product->sku,
            'item_name' => $product->name,
            'item_brand' => $product->brand?->name,
            'item_category' => $product->category?->name,
            'price' => (float) ($price ?? $product->price),
            'quantity' => $quantity,
        ], fn ($v) => $v !== null);
    }

    public static function orderParams(Order $order): array
    {
        return [
            'transaction_id' => $order->order_number,
            'value' => (float) $order->total,
            'currency' => 'BDT',
            'shipping' => (float) $order->delivery_charge,
            'coupon' => $order->coupon_code,
            'items' => $order->items->map(fn ($i) => [
                'item_id' => $i->sku,
                'item_name' => $i->name,
                'price' => (float) $i->price,
                'quantity' => $i->quantity,
            ])->all(),
        ];
    }
}
