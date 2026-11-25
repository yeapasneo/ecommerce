<?php

namespace App\Services;

use App\Messaging\MailConfig;
use App\Messaging\Sms\SmsManager;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Returns and warranty claims: requested → approved → item received (stock back)
 * → refunded / completed, or rejected / cancelled.
 */
class Returns
{
    public function __construct(protected SmsManager $sms) {}

    public static function returnDays(): int
    {
        return max(0, (int) Setting::get('return_days', 7));
    }

    /**
     * What can still be claimed from an order, per item: returnable and warranty quantities,
     * the warranty end date and serials.
     */
    public function claimable(Order $order): array
    {
        $order->loadMissing('items', 'returns.items');
        $delivered = $order->deliveredAt();
        $returnOpen = $delivered && $delivered->copy()->addDays(self::returnDays())->endOfDay()->isFuture();
        $taken = fn (string $type, int $itemId) => (int) $order->returns->where('type', $type)->whereNotIn('status', ['rejected', 'cancelled'])
            ->filter(fn ($r) => $type === 'return' || in_array($r->status, ReturnRequest::OPEN, true))
            ->flatMap->items->where('order_item_id', $itemId)->sum('quantity');

        return $order->items->mapWithKeys(function ($item) use ($delivered, $returnOpen, $taken) {
            $months = (int) $item->warranty_months;
            $warrantyEnds = $delivered && $months > 0 ? $delivered->copy()->addMonths($months) : null;

            return [$item->id => [
                'return' => $returnOpen ? max(0, $item->quantity - $taken('return', $item->id)) : 0,
                'warranty' => $warrantyEnds?->isFuture() ? max(0, $item->quantity - $taken('warranty', $item->id)) : 0,
                'warranty_ends' => $warrantyEnds?->toDateString(),
                'serials' => $item->serials ?? [],
            ]];
        })->all();
    }

    /**
     * Open a request. $items: [order_item_id => ['quantity' => n, 'serial' => ?]].
     * Staff ($byStaff) may go past the return window and warranty period.
     */
    public function open(Order $order, string $type, array $items, string $reason, ?string $details, ?int $userId, bool $byStaff = false): ReturnRequest
    {
        if ($order->status !== 'delivered' && ! $byStaff) {
            throw ValidationException::withMessages(['items' => 'Returns can be requested once the order is delivered.']);
        }
        $allowed = $this->claimable($order);
        $lines = [];
        foreach ($items as $itemId => $row) {
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $item = $order->items->firstWhere('id', (int) $itemId);
            if (! $item) {
                throw ValidationException::withMessages(['items' => 'That item is not part of this order.']);
            }
            $max = $byStaff ? $item->quantity : ($allowed[$item->id][$type] ?? 0);
            if ($qty > $max) {
                throw ValidationException::withMessages(["items.{$itemId}.quantity" => $max === 0
                    ? ($type === 'return' ? "{$item->name} can no longer be returned." : "{$item->name} is not under warranty.")
                    : "You can claim at most {$max} of {$item->name}."]);
            }
            $lines[] = ['order_item_id' => $item->id, 'quantity' => $qty, 'serial' => self::cleanSerial($row['serial'] ?? null)];
        }
        if (! $lines) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item.']);
        }

        return DB::transaction(function () use ($order, $type, $lines, $reason, $details, $userId) {
            $request = ReturnRequest::create([
                'number' => ReturnRequest::nextNumber(), 'order_id' => $order->id, 'user_id' => $order->user_id,
                'type' => $type, 'reason' => $reason, 'details' => $details,
            ]);
            $request->items()->createMany($lines);
            $request->events()->create(['status' => 'requested', 'comment' => $details, 'user_id' => $userId]);
            $order->histories()->create(['status' => $order->status, 'comment' => ReturnRequest::TYPES[$type]." {$request->number} requested."]);

            return $request;
        });
    }

    public function approve(ReturnRequest $r, ?string $note, int $staffId, ?string $resolution = null): void
    {
        $this->expect($r, ['requested']);
        $this->move($r, 'approved', $note, $staffId, ['resolution' => $resolution ?? ($r->type === 'return' ? 'refund' : 'repair')]);
    }

    public function reject(ReturnRequest $r, string $note, int $staffId): void
    {
        $this->expect($r, ['requested', 'approved']);
        $this->move($r, 'rejected', $note, $staffId);
    }

    /** The customer withdraws a request that hasn't been handled yet. */
    public function cancel(ReturnRequest $r, ?int $userId): void
    {
        $this->expect($r, ['requested']);
        $this->move($r, 'cancelled', 'Cancelled by the customer.', $userId, notify: false);
    }

    /** Items arrived back. Items marked for restock go back on sale. */
    public function receive(ReturnRequest $r, array $restock, ?string $note, int $staffId): void
    {
        $this->expect($r, ['approved']);
        DB::transaction(function () use ($r, $restock, $note, $staffId) {
            $r->load('items.orderItem');
            $any = false;
            Stock::as('return', $r, function () use ($r, $restock, &$any) {
                foreach ($r->items as $line) {
                    $back = (bool) ($restock[$line->id] ?? false);
                    $line->update(['restock' => $back]);
                    if (! $back || ! $line->orderItem) {
                        continue;
                    }
                    $any = true;
                    $oi = $line->orderItem;
                    if ($oi->variant_id && ($v = ProductVariant::lockForUpdate()->find($oi->variant_id))) {
                        $v->update(['stock' => $v->stock + $line->quantity]);
                    }
                    if ($oi->product_id && ($p = Product::lockForUpdate()->find($oi->product_id))) {
                        $p->update(['stock' => $p->stock + $line->quantity] + ($p->stock_status === 'out_of_stock' ? ['stock_status' => 'in_stock'] : []));
                    }
                }
            });
            $r->update(['restocked' => $any]);
            $this->move($r, 'received', $note, $staffId, notify: false);
        });
    }

    /** Money back to the customer (full or part), recorded on the order. */
    public function refund(ReturnRequest $r, float $amount, string $method, ?string $reference, ?string $note, int $staffId): void
    {
        $this->expect($r, ['approved', 'received']);
        DB::transaction(function () use ($r, $amount, $method, $reference, $note, $staffId) {
            $order = Order::lockForUpdate()->findOrFail($r->order_id);
            $max = round(max(0, (float) $order->paid_amount - (float) $order->refunded_amount), 2);
            if ($amount <= 0 || $amount > $max) {
                throw ValidationException::withMessages(['amount' => $max > 0 ? 'The refund can be at most ৳'.number_format($max, 2).' (paid minus earlier refunds).' : 'Nothing has been paid on this order, so there is nothing to refund.']);
            }
            $refunded = (float) $order->refunded_amount + $amount;
            $order->update(['refunded_amount' => $refunded] + ($refunded >= (float) $order->paid_amount ? ['payment_status' => 'refunded'] : []));
            $order->histories()->create(['status' => $order->status, 'comment' => 'Refunded ৳'.number_format($amount, 2).' by '.(ReturnRequest::REFUND_METHODS[$method] ?? $method)." ({$r->number})."]);
            $this->move($r, 'refunded', $note, $staffId, [
                'refund_amount' => $r->refund_amount + $amount, 'refund_method' => $method, 'refund_reference' => $reference, 'refunded_at' => now(), 'resolution' => 'refund',
            ]);
        });
    }

    /** Warranty repaired/replaced, or a return closed without a refund. */
    public function complete(ReturnRequest $r, ?string $resolution, ?string $note, int $staffId): void
    {
        $this->expect($r, ['approved', 'received', 'refunded']);
        $this->move($r, 'completed', $note, $staffId, $resolution ? ['resolution' => $resolution] : []);
    }

    public static function cleanSerial(?string $serial): ?string
    {
        $serial = strtoupper(trim((string) preg_replace('/\s+/', '', (string) $serial)));

        return $serial === '' ? null : mb_substr($serial, 0, 100);
    }

    protected function expect(ReturnRequest $r, array $statuses): void
    {
        if (! in_array($r->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'This request is '.strtolower(ReturnRequest::STATUSES[$r->status] ?? $r->status).', so that step is not possible.']);
        }
    }

    protected function move(ReturnRequest $r, string $status, ?string $note, ?int $userId, array $attrs = [], bool $notify = true): void
    {
        $r->update(['status' => $status, 'handled_by' => $userId ?? $r->handled_by] + ($note !== null && $status !== 'cancelled' ? ['staff_note' => $note] : []) + $attrs);
        $r->events()->create(['status' => $status, 'comment' => $note, 'user_id' => $userId]);
        if ($notify) {
            $this->notify($r);
        }
    }

    /** Tell the customer by SMS / email (Settings → Notifications → Returns). */
    public function notify(ReturnRequest $r, bool $deferred = false): void
    {
        if (! Setting::enabled('notify_returns')) {
            return;
        }
        if (! $deferred && DB::transactionLevel() > 0) { // send once the change is saved
            DB::afterCommit(fn () => $this->notify($r->fresh(), true));

            return;
        }
        $order = $r->order;
        $label = strtolower(ReturnRequest::STATUSES[$r->status] ?? $r->status);
        $text = 'Your '.strtolower(ReturnRequest::TYPES[$r->type])." {$r->number} for order #{$order->order_number} is {$label}."
            .($r->status === 'refunded' ? ' Refund: Tk '.number_format($r->refund_amount).'.' : '')
            .($r->staff_note ? " {$r->staff_note}" : '').' - '.Setting::get('site_name');
        $send = function (string $channel, string $to, callable $fn) use ($order, $text) {
            try {
                $fn();
                NotificationLog::create(['order_id' => $order->id, 'channel' => $channel, 'event' => 'return_update', 'recipient' => $to, 'message' => $text, 'status' => 'sent']);
            } catch (Throwable $e) {
                NotificationLog::create(['order_id' => $order->id, 'channel' => $channel, 'event' => 'return_update', 'recipient' => $to, 'message' => $text, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        };
        if ($this->sms->isConfigured() && Phone::isValid($order->phone)) {
            $send('sms', $order->phone, fn () => $this->sms->send($order->phone, mb_substr($text, 0, 300)));
        }
        if (filled($order->email)) {
            $send('email', $order->email, function () use ($order, $text, $r) {
                MailConfig::configure();
                Mail::raw($text."\n\nSee the details in your account: ".route('account.returns'), fn ($m) => $m->to($order->email)->subject("Update on {$r->number}"));
            });
        }
    }
}
