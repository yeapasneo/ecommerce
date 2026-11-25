<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\InventoryController;
use App\Messaging\MailConfig;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Daily email listing products at or below the low-stock level. */
class LowStockAlert extends Command
{
    protected $signature = 'stock:low-alert';

    protected $description = 'Email the shop a list of products that are running low';

    public function handle(): int
    {
        $to = Setting::get('low_stock_email') ?: Setting::get('notify_admin_email');
        if (! Setting::enabled('low_stock_alerts') || ! $to) {
            $this->line('Low-stock alerts are off or no email is set (Settings → General).');

            return self::SUCCESS;
        }
        $rows = InventoryController::lowStock();
        if ($rows->isEmpty()) {
            $this->info('Nothing is low on stock.');

            return self::SUCCESS;
        }
        $site = Setting::get('site_name');
        $list = $rows->take(100)->map(fn ($r) => sprintf('• %s (%s): %d left', $r['name'], $r['sku'] ?: '-', $r['stock']))->implode("\n");
        $more = $rows->count() > 100 ? "\n…and ".($rows->count() - 100).' more.' : '';
        $body = "{$rows->count()} product(s) are at or below ".InventoryController::threshold()." in stock:\n\n{$list}{$more}\n\nReorder from the admin panel: ".url('/admin/inventory');

        MailConfig::configure();
        Mail::raw($body, fn ($m) => $m->to($to)->subject("Low stock: {$rows->count()} product(s) at {$site}"));
        $this->info("Sent to {$to}.");

        return self::SUCCESS;
    }
}
