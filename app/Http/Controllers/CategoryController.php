<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductListing;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $category = Category::active()->where('slug', $slug)->with('children:id,parent_id,name,slug')->firstOrFail();
        $attrs = $category->effectiveFilterAttributes();
        $base = Product::active()->whereIn('category_id', $category->descendantIds());

        return Inertia::render('Catalog/Listing', [
            'title' => $category->name,
            'description' => $category->description,
            'breadcrumbs' => $category->breadcrumbs(),
            'subcategories' => $category->children,
            ...(new ProductListing($request))->build($base, $attrs),
        ]);
    }

    public function brand(Request $request, string $slug)
    {
        $brand = Brand::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $base = Product::active()->where('brand_id', $brand->id);

        return Inertia::render('Catalog/Listing', [
            'title' => $brand->name,
            'breadcrumbs' => [['name' => 'Brands', 'url' => route('brands.index')], ['name' => $brand->name]],
            'subcategories' => [],
            ...(new ProductListing($request))->build($base, [], false),
        ]);
    }

    public function brands()
    {
        return Inertia::render('Catalog/Brands', [
            'brands' => Brand::where('is_active', true)->withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get(),
        ]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->input('q'));
        $base = Product::active()->when($term !== '', function ($q) use ($term) {
            $words = preg_split('/\s+/', $term);
            foreach ($words as $w) {
                $q->where(fn ($qq) => $qq->where('name', 'ilike', "%{$w}%")->orWhere('sku', 'ilike', "%{$w}%")->orWhere('model', 'ilike', "%{$w}%"));
            }
        });

        return Inertia::render('Catalog/Listing', [
            'title' => $term !== '' ? "Search: {$term}" : 'All Products',
            'breadcrumbs' => [['name' => 'Search']],
            'subcategories' => [],
            'searchTerm' => $term,
            ...(new ProductListing($request))->build($base),
        ]);
    }

    /** JSON suggestions for the header search box. */
    public function suggest(Request $request)
    {
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Product::active()->where('name', 'ilike', "%{$term}%")->orderByDesc('views')->take(8)
                ->get()->map(fn ($p) => ['name' => $p->name, 'slug' => $p->slug, 'price' => $p->price, 'image_url' => $p->image_url])
        );
    }
}
