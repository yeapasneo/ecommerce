<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ProductFeed;

/** Public catalogue feed URLs for Google Merchant Center and Meta Commerce Manager. */
class FeedController extends Controller
{
    public function show(ProductFeed $feed)
    {
        abort_unless(Setting::enabled('feeds_enabled'), 404);
        if (! is_file(ProductFeed::path())) {
            $feed->generate(); // first request after turning feeds on
        }

        return response()->file(ProductFeed::path(), [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }
}
