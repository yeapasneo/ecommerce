<?php

namespace App\Console\Commands;

use App\Couriers\CourierManager;
use App\Models\Order;
use Illuminate\Console\Command;
use Throwable;

/** Backup for missed webhooks: asks the courier for the status of parcels still on the way. */
class SyncCourierStatuses extends Command
{
    protected $signature = 'couriers:sync {--limit=200}';

    protected $description = 'Refresh courier status for parcels that are not delivered yet';

    public const FINAL = ['delivered', 'partial_delivered', 'partial_delivery', 'cancelled', 'return', 'returned', 'paid_return', 'delivery_failed'];

    public function handle(CourierManager $couriers): int
    {
        $orders = Order::whereNotNull('courier')->whereNotNull('courier_consignment_id')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->where(fn ($q) => $q->whereNull('courier_status')->orWhereNotIn('courier_status', self::FINAL))
            ->where('courier_sent_at', '>=', now()->subDays(45))
            ->orderBy('courier_status_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $updated = $failed = 0;
        foreach ($orders as $order) {
            try {
                $before = $order->courier_status;
                $couriers->driver($order->courier)->refresh($order);
                $updated += $order->courier_status !== $before ? 1 : 0;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("#{$order->order_number}: {$e->getMessage()}");
            }
        }
        $this->info("Checked {$orders->count()} parcel(s): {$updated} changed, {$failed} failed.");

        return self::SUCCESS;
    }
}
