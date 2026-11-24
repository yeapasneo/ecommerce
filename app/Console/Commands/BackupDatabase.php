<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/** Compressed pg_dump of the database into storage/app/backups, keeping the newest N. */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup {--keep=14}';

    protected $description = 'Back up the PostgreSQL database';

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        if (($db['driver'] ?? null) !== 'pgsql') {
            $this->error('Backups are only set up for PostgreSQL.');

            return self::FAILURE;
        }
        File::ensureDirectoryExists(self::directory());
        $file = self::directory().'/'.$db['database'].'-'.now()->format('Y-m-d-His').'.dump';

        $result = Process::timeout(1800)
            ->env(['PGPASSWORD' => (string) ($db['password'] ?? '')])
            ->run(['pg_dump', '--format=custom', '--no-owner', '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--file='.$file, $db['database']]);

        if (! $result->successful()) {
            File::delete($file);
            $this->error('pg_dump failed: '.trim($result->errorOutput()));
            Cache::forever('backup:last_error', ['at' => now()->toIso8601String(), 'error' => trim($result->errorOutput())]);

            return self::FAILURE;
        }

        // Keep only the newest backups.
        collect(File::glob(self::directory().'/*.dump'))->sortDesc()->slice((int) $this->option('keep'))->each(fn ($old) => File::delete($old));
        Cache::forget('backup:last_error');

        $this->info('Backup saved: '.basename($file).' ('.round(filesize($file) / 1048576, 1).' MB)');

        return self::SUCCESS;
    }
}
