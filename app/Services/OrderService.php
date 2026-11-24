<?php

namespace App\Services;

use App\Messaging\OrderNotifier;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(protected OrderNotifier $notifier) {}

    /**
     * Create an order from product lines, checking and reserving stock.
     *
     * @param  array  $lines  [['product_id' => int, 'quantity' => int, 'price' => ?float]] (price defaults to the product price)
     * @param  callable  $build  fn (float $subtotal): array of order attributes (totals, customer, status…)
     * @param  array  $options  error_key, history (comment), notify (bool), after (fn (Order) inside the transaction)
     */
    public function place(array $lines, callable $build, array $options = []): Order
    {
        $errorKey = $options['error_key'] ?? 'cart';

        $order = DB::transaction(function () use ($lines, $build, $options, $errorKey) {
            $subtotal = 0;
            $items = [];
            foreach ($lines as $line) {
                $product = Product::lockForUpdate()->find($line['product_id']);
                $qty = (int) $line['quantity'];
                if (! $product || ! $product->isPurchasable()) {
                    throw ValidationException::withMessages([$errorKey => ($product?->name ?? 'A product').' is no longer available.']);
                }
                if ($product->stock_status === 'in_stock' && $product->stock < $qty) {
                    throw ValidationException::withMessages([$errorKey => "Only {$product->stock} unit(s) of {$product->name} are in stock."]);
                }
                $price = isset($line['price']) ? (float) $line['price'] : (float) $product->price;
                $subtotal += $price * $qty;
                $items[] = [$product, $qty, $price];
            }

            $order = Order::create($build($subtotal) + ['order_number' => Order::generateNumber()]);

            foreach ($items as [$product, $qty, $price]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $price,
                    'quantity' => $qty,
                    'total' => $price * $qty,
                ]);
                if ($product->stock_status === 'in_stock') {
                    $product->decrement('stock', $qty);
                    if ($product->stock <= 0) {
                        $product->update(['stock_status' => 'out_of_stock']);
                    }
                }
            }
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
                foreach ($order->items as $item) {
                    if ($item->product_id && ($p = Product::lockForUpdate()->find($item->product_id))) {
                        $p->increment('stock', $item->quantity);
                        if ($p->stock_status === 'out_of_stock' && $p->stock > 0) {
                            $p->update(['stock_status' => 'in_stock']);
                        }
                    }
                }
            }
            $updates = ['status' => $status];
            if ($status === 'delivered' && $order->payment_method === 'cod') {
                $updates['payment_status'] = 'paid';
            }
            $order->update($updates);
            $order->histories()->create(['status' => $status, 'comment' => $comment]);
        });

        $this->notifier->statusChanged($order, $status);
    }
}
