<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\FlashSales;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $featuredCategories = Category::active()->where('is_featured', true)->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']);

        $site = Setting::get('site_name');
        seo()->canonical(url('/'))
            ->schema(array_filter([
                '@type' => 'Organization',
                'name' => $site,
                'url' => url('/'),
                'logo' => url('/favicon.svg'),
                'contactPoint' => ['@type' => 'ContactPoint', 'telephone' => Setting::get('phone'), 'contactType' => 'customer service', 'areaServed' => 'BD'],
                'address' => ['@type' => 'PostalAddress', 'streetAddress' => Setting::get('address'), 'addressCountry' => 'BD'],
                'sameAs' => array_values(array_filter([Setting::get('facebook'), Setting::get('youtube'), Setting::get('instagram')])) ?: null,
            ]))
            ->schema([
                '@type' => 'WebSite',
                'name' => $site,
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/search').'?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ]);

        return Inertia::render('Home', [
            'sliders' => Banner::where('is_active', true)->where('position', 'slider')->orderBy('sort_order')->get(),
            'sideBanners' => Banner::where('is_active', true)->where('position', 'side')->orderBy('sort_order')->take(2)->get(),
            'featuredCategories' => $featuredCategories,
            'featuredProducts' => Product::active()->where('is_featured', true)->latest('id')->take(12)->get()->map->toCard(),
            'flashSale' => $this->flashSale(),
            'newArrivals' => Product::active()->latest('id')->take(6)->get()->map->toCard(),
            'brands' => Brand::where('is_active', true)->orderBy('name')->take(12)->get(),
        ]);
    }

    /** Products in the running flash sale(s), ending soonest first. */
    protected function flashSale(): ?array
    {
        $sales = app(FlashSales::class);
        $ids = collect($sales->active())->filter(fn ($s, $id) => $sales->for((int) $id))->sortBy('ends_at')->keys()->take(12);
        if ($ids->isEmpty()) {
            return null;
        }
        $first = $sales->for((int) $ids->first());

        return [
            'title' => $first['title'],
            'ends_at' => $first['ends_at'],
            'products' => Product::active()->whereIn('id', $ids)->get()->map->toCard()->values(),
        ];
    }
}
