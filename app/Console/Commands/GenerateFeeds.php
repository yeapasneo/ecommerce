<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\ProductFeed;
use Illuminate\Console\Command;

/** Rebuild the Google Shopping / Facebook catalogue feed. */
class GenerateFeeds extends Command
{
    protected $signature = 'feeds:generate {--force : Build even if feeds are turned off}';

    protected $description = 'Write the product feed read by Google Merchant Center and Meta Commerce Manager';

    public function handle(ProductFeed $feed): int
    {
        if (! $this->option('force') && ! Setting::enabled('feeds_enabled')) {
            $this->line('Product feeds are off (Settings → SEO).');

            return self::SUCCESS;
        }
        $this->info('Feed written with '.$feed->generate().' products.');

        return self::SUCCESS;
    }
}
