<?php

namespace App\Console\Commands;

use App\Support\CacheDriver;
use Illuminate\Console\Command;

/** Show which cache the shop is using, and check Redis again right now. */
class CacheDriverStatus extends Command
{
    protected $signature = 'cache:driver';

    protected $description = 'Show the cache store in use (CACHE_STORE=auto picks Redis when available, otherwise file) and re-check Redis';

    public function handle(): int
    {
        $configured = env('CACHE_STORE', 'auto');
        $this->line("CACHE_STORE: <info>{$configured}</info>");

        $ping = CacheDriver::ping(config('database.redis', []));
        $this->line('Redis client: '.(CacheDriver::hasClient() ? '<info>installed</info> ('.(extension_loaded('redis') ? 'phpredis' : 'predis').')' : '<comment>not installed</comment> (install the php-redis extension)'));
        $this->line('Redis server: '.($ping === true ? '<info>answering</info>' : "<comment>not reachable</comment> ({$ping})"));

        if ($configured === 'auto') {
            CacheDriver::forget(CacheDriver::stateFile());
            $store = CacheDriver::resolve(config('database.redis', []), CacheDriver::stateFile());
            $this->line('In use: <info>'.($store === 'redis' ? 'Redis (falls back to file if Redis stops)' : 'File cache').'</info>');
        } else {
            $this->line("In use: <info>{$configured}</info> (set CACHE_STORE=auto to choose automatically)");
        }

        return self::SUCCESS;
    }
}
