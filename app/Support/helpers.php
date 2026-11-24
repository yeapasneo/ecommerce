<?php

use App\Support\Analytics;
use App\Support\Seo;
use Illuminate\Support\Str;

if (! function_exists('media_url')) {
    /** Public URL for a stored media path (or pass through absolute URLs). */
    function media_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }
}

if (! function_exists('seo')) {
    function seo(): Seo
    {
        return app(Seo::class);
    }
}

if (! function_exists('analytics')) {
    function analytics(): Analytics
    {
        return app(Analytics::class);
    }
}
