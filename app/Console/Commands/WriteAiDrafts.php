<?php

namespace App\Console\Commands;

use App\Ai\AiClient;
use App\Ai\DraftQueue;
use App\Models\Setting;
use Illuminate\Console\Command;

/** Hourly: queue AI drafts for the products that need texts most (when turned on in Settings → AI). */
class WriteAiDrafts extends Command
{
    protected $signature = 'ai:write-drafts {--limit= : How many products (default: the per-hour setting)} {--force : Run even if automatic writing is off}';

    protected $description = 'Queue AI-written product texts for staff to review';

    public function handle(AiClient $ai, DraftQueue $queue): int
    {
        if (! $this->option('force') && ! Setting::enabled('ai_writer_auto')) {
            $this->line('Automatic writing is off (Settings → AI).');

            return self::SUCCESS;
        }
        if (! $ai->isConfigured() || $ai->limitReached()) {
            $this->warn($ai->isConfigured() ? 'Daily AI limit reached.' : 'AI is not set up.');

            return self::SUCCESS;
        }
        $count = $queue->queue(max(1, min(200, (int) ($this->option('limit') ?: Setting::get('ai_writer_per_hour', 10)))));
        $this->info("Queued {$count} product(s).");

        return self::SUCCESS;
    }
}
