<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\Setting;
use App\Services\CartService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Full-page cache for visitors who aren't signed in and have nothing personal on the
 * page (empty cart and compare list, no messages). Pages are kept for a few minutes
 * and dropped as soon as a product, category, banner, page or setting changes.
 * Runs inside the web middleware, so session and CSRF cookies are still set.
 */
class CacheGuestPages
{
    public const VERSION_KEY = 'page_cache_version';

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cacheable($request)) {
            return $next($request);
        }
        $key = $this->key($request);
        if ($hit = Cache::get($key)) {
            if ($request->route()?->getName() === 'product.show') {
                Product::where('slug', $request->route('slug'))->increment('views');
            }

            return response($hit['body'], 200, $hit['headers'] + ['X-Page-Cache' => 'HIT']);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 200 && $this->stillGuest($request)) {
            Cache::put($key, [
                'body' => $response->getContent(),
                'headers' => array_filter([
                    'Content-Type' => $response->headers->get('Content-Type'),
                    'X-Inertia' => $response->headers->get('X-Inertia'),
                    'Vary' => $response->headers->get('Vary'),
                ]),
            ], now()->addMinutes(max(1, (int) Setting::get('page_cache_minutes', 5))));
            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    protected function cacheable(Request $request): bool
    {
        return Setting::enabled('page_cache_enabled')
            && $request->isMethod('GET')
            && ! $request->user()
            && $this->guestHasNothingPersonal($request);
    }

    /** Nothing on the page is about this visitor: no compare list, cart, messages or pending analytics. */
    protected function guestHasNothingPersonal(Request $request): bool
    {
        $session = $request->session();

        return empty($session->get('compare'))
            && ! $session->has('success') && ! $session->has('error') && ! $session->has('errors')
            && empty($session->get('analytics_queue'))
            && app(CartService::class)->count() === 0;
    }

    /** The page didn't sign anyone in or fill a cart while rendering. */
    protected function stillGuest(Request $request): bool
    {
        return ! $request->user() && ! $request->session()->has('errors');
    }

    protected function key(Request $request): string
    {
        return 'page:'.Cache::get(self::VERSION_KEY, 1).':'.md5(implode('|', [
            $request->fullUrl(),
            Vite::manifestHash(), // a new build/deploy changes the asset file names
            app()->getLocale(),
            $request->header('X-Inertia', ''),
            $request->header('X-Inertia-Version', ''),
            $request->header('X-Inertia-Partial-Data', ''),
            $request->header('X-Inertia-Partial-Component', ''),
            $request->header('X-Inertia-Except-Once-Props', ''), // the menu is left out once the browser has it
            $request->header('Accept', ''),
        ]));
    }
}
