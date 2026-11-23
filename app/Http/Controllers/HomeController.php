<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $featuredCategories = Category::active()->where('is_featured', true)->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']);

        return Inertia::render('Home', [
            'sliders' => Banner::where('is_active', true)->where('position', 'slider')->orderBy('sort_order')->get(),
            'sideBanners' => Banner::where('is_active', true)->where('position', 'side')->orderBy('sort_order')->take(2)->get(),
            'featuredCategories' => $featuredCategories,
            'featuredProducts' => Product::active()->where('is_featured', true)->latest('id')->take(12)->get()->map->toCard(),
            'newArrivals' => Product::active()->latest('id')->take(6)->get()->map->toCard(),
            'brands' => Brand::where('is_active', true)->orderBy('name')->take(12)->get(),
        ]);
    }
}
