<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\AbandonedCarts;
use Illuminate\Console\Command;

class RemindAbandonedCarts extends Command
{
    protected $signature = 'carts:remind';

    protected $description = 'Send one reminder for checkouts left unfinished';

    public function handle(AbandonedCarts $carts): int
    {
        if (! Setting::enabled('abandoned_cart_enabled')) {
            $this->info('Abandoned cart reminders are switched off.');

            return self::SUCCESS;
        }
        $due = $carts->due();
        $sent = $due->filter(fn ($record) => count($carts->remind($record)) > 0)->count();
        $this->info("Reminded {$sent} of {$due->count()} abandoned cart(s).");

        return self::SUCCESS;
    }
}
