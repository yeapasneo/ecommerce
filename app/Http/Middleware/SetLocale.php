<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Storefront language: the visitor's choice, else the shop default. The admin panel stays in English. */
class SetLocale
{
    public const LOCALES = ['en' => 'English', 'bn' => 'বাংলা'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin*')) {
            $locale = session('locale') ?? $request->cookie('locale') ?? Setting::get('default_locale', 'en');
            app()->setLocale(isset(self::LOCALES[$locale]) ? $locale : 'en');
        }

        return $next($request);
    }
}
