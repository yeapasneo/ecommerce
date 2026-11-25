<?php

namespace App\Http\Middleware;

use App\Ai\AiClient;
use App\Ai\ShopAssistant;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Question;
use App\Models\ReturnRequest;
use App\Models\Review;
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
                'user' => $user ? $user->only('id', 'name', 'email', 'phone', 'is_admin') + ($user->is_admin ? ['role' => $user->roleLabel(), 'permissions' => $user->permissions()] : []) : null,
            ],
            'settings' => fn () => Setting::public(),
            'locale' => app()->getLocale(),
            'aiReady' => fn () => $request->is('admin*') && $request->user()?->is_admin && app(AiClient::class)->isConfigured(),
            'assistant' => fn () => ! $request->is('admin*') && ShopAssistant::enabled()
                ? ['name' => ShopAssistant::name(), 'greeting' => Setting::get('ai_chat_greeting')] : null,
            'translations' => fn () => app()->getLocale() !== 'en' && ! $request->is('admin*')
                ? Cache::rememberForever('translations:'.app()->getLocale().':'.@filemtime(lang_path(app()->getLocale().'.json')), fn () => json_decode((string) @file_get_contents(lang_path(app()->getLocale().'.json')), true) ?: [])
                : [],
            'seo' => fn () => $request->is('admin*') ? null : seo()->toArray(),
            'analytics' => fn () => $request->is('admin*') ? [] : analytics()->pull(),
            'menu' => fn () => $request->is('admin*') ? [] : Cache::remember('category_menu', 600, fn () => Category::menuTree()),
            'cartCount' => fn () => app(CartService::class)->count(),
            'compareCount' => fn () => count(session('compare', [])),
            // Sidebar badges in the admin panel.
            'adminCounts' => fn () => $request->is('admin*') && $user?->is_admin ? [
                'orders' => Order::where('status', 'pending')->count(),
                'reviews' => Review::where('is_approved', false)->count(),
                'questions' => Question::whereNull('answer')->count(),
                'returns' => ReturnRequest::where('status', 'requested')->count(),
                'messages' => ContactMessage::where('is_read', false)->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
