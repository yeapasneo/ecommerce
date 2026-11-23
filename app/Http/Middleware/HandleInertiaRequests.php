<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? $user->only('id', 'name', 'email', 'phone', 'is_admin') : null,
            ],
            'settings' => fn () => Setting::all_settings(),
            'menu' => fn () => $request->is('admin*') ? [] : Cache::remember('category_menu', 600, fn () => Category::menuTree()),
            'cartCount' => fn () => app(CartService::class)->count(),
            'compareCount' => fn () => count(session('compare', [])),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
