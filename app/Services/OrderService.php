<?php

namespace App\Services;

use App\Messaging\OrderNotifier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(protected OrderNotifier $notifier) {}

    /**
     * Create an order from product lines, checking and reserving stock.
     *
     * @param  array  $lines  [['product_id' => int, 'variant_id' => ?int, 'quantity' => int, 'price' => ?float]]
     * @param  callable  $build  fn (float $subtotal): array of order attributes (totals, customer, status…)
     * @param  array  $options  error_key, history (comment), notify (bool), after (fn (Order) inside the transaction)
     */
    public function place(array $lines, callable $build, array $options = []): Order
    {
        $order = DB::transaction(function () use ($lines, $build, $options) {
            [$subtotal, $items] = $this->reserve($lines, $options['error_key'] ?? 'cart');
            $order = Order::create($build($subtotal) + ['order_number' => Order::generateNumber()]);
            $this->writeItems($order, $items);
            $order->histories()->create(['status' => $order->status, 'comment' => $options['history'] ?? 'Order placed.']);
            if (isset($options['after'])) {
                $options['after']($order);
            }

            return $order;
        });

        if ($options['notify'] ?? true) {
            $this->notifier->notify($order, 'order_placed');
        }

        return $order;
    }

    /** Replace an order's items and details. Old items go back to stock before the new ones are reserved. */
    public function revise(Order $order, array $lines, callable $build, string $comment): Order
    {
        return DB::transaction(function () use ($order, $lines, $build, $comment) {
            $order = Order::lockForUpdate()->with('items')->findOrFail($order->id);
            $before = (float) $order->total;
            $this->restock($order);
            $order->items()->delete();

            [$subtotal, $items] = $this->reserve($lines, 'items');
            $order->update($build($subtotal));
            $this->writeItems($order, $items);

            $change = $before !== (float) $order->total ? ' Total ৳'.number_format($before).' → ৳'.number_format((float) $order->total).'.' : '';
            $order->histories()->create(['status' => $order->status, 'comment' => $comment.$change]);

            return $order->fresh('items');
        });
    }

    /** Lock, validate and price each line. */
    protected function reserve(array $lines, string $errorKey): array
    {
        $subtotal = 0;
        $items = [];
        foreach ($lines as $line) {
            $product = Product::lockForUpdate()->find($line['product_id']);
            $qty = (int) $line['quantity'];
            $variant = ! empty($line['variant_id']) && $product
                ? ProductVariant::lockForUpdate()->where('product_id', $product->id)->find($line['variant_id'])
                : null;
            $variant?->setRelation('product', $product);
            $label = $product?->name.($variant ? " ({$variant->name})" : '');

            if (! $product || ! $product->is_active || ($product->has_variants && ! $variant)) {
                throw ValidationException::withMessages([$errorKey => $product && $product->has_variants && ! $variant
                    ? "Choose an option for {$product->name}."
                    : ($product?->name ?? 'A product').' is no longer available.']);
            }
            if ($variant) {
                if (! $variant->is_active || ($product->stock_status !== 'pre_order' && $variant->stock < $qty)) {
                    throw ValidationException::withMessages([$errorKey => $variant->stock > 0 && $variant->is_active
                        ? "Only {$variant->stock} unit(s) of {$label} are in stock."
                        : "{$label} is out of stock."]);
                }
            } elseif (! $product->isPurchasable()) {
                throw ValidationException::withMessages([$errorKey => "{$product->name} is no longer available."]);
            } elseif ($product->stock_status === 'in_stock' && $product->stock < $qty) {
                throw ValidationException::withMessages([$errorKey => "Only {$product->stock} unit(s) of {$product->name} are in stock."]);
            }

            $price = isset($line['price']) ? (float) $line['price'] : ($variant ? $variant->price : (float) $product->price);
            $subtotal += $price * $qty;
            $items[] = [$product, $variant, $qty, $price];
        }

        return [$subtotal, $items];
    }

    protected function writeItems(Order $order, array $items): void
    {
        foreach ($items as [$product, $variant, $qty, $price]) {
            $order->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->name,
                'variant_name' => $variant?->name,
                'sku' => $variant?->sku ?: $product->sku,
                'price' => $price,
                'cost_price' => $variant?->cost_price ?? $product->cost_price,
                'quantity' => $qty,
                'total' => $price * $qty,
            ]);
            if ($variant) {
                $variant->decrement('stock', min($qty, $variant->stock));
            }
            if ($product->stock_status === 'in_stock' || $variant) {
                $product->decrement('stock', min($qty, max(0, $product->stock)));
                if ($product->stock <= 0 && $product->stock_status === 'in_stock') {
                    $product->update(['stock_status' => 'out_of_stock']);
                }
            }
        }
    }

    /** Return an order's items to stock. */
    protected function restock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->variant_id && ($v = ProductVariant::lockForUpdate()->find($item->variant_id))) {
                $v->increment('stock', $item->quantity);
            }
            if ($item->product_id && ($p = Product::lockForUpdate()->find($item->product_id))) {
                $p->increment('stock', $item->quantity);
                if ($p->stock_status === 'out_of_stock' && $p->stock > 0) {
                    $p->update(['stock_status' => 'in_stock']);
                }
            }
        }
    }

    /** Changes order status, records history, restocks items when an order is cancelled and notifies the customer. */
    public function changeStatus(Order $order, string $status, ?string $comment = null): void
    {
        if ($order->status === $status) {
            if ($comment) {
                $order->histories()->create(['status' => $status, 'comment' => $comment]);
            }

            return;
        }

        DB::transaction(function () use ($order, $status, $comment) {
            if ($status === 'cancelled' && $order->status !== 'cancelled') {
                $this->restock($order);
            }
            $updates = ['status' => $status];
            if ($status === 'delivered' && $order->payment_method === 'cod') {
                $updates += ['payment_status' => 'paid', 'paid_amount' => $order->total, 'paid_at' => $order->paid_at ?? now()];
            }
            $order->update($updates);
            $order->histories()->create(['status' => $status, 'comment' => $comment]);
        });

        $this->notifier->statusChanged($order, $status);
    }
}
