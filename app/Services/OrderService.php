<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /** Changes order status, records history and restocks items when an order is cancelled. */
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
    }
}
