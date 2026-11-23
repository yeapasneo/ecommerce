<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductSpec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Applies storefront filters/sorting to a product query and builds the filter sidebar. */
class ProductListing
{
    public const SORTS = [
        'default' => 'Default',
        'price_asc' => 'Price (Low > High)',
        'price_desc' => 'Price (High > Low)',
        'newest' => 'Newest',
        'popular' => 'Popularity',
    ];

    public function __construct(protected Request $request) {}

    public function build(Builder $base, array $filterAttributes = [], bool $withBrandFilter = true): array
    {
        $r = $this->request;
        $baseForFacets = clone $base;
        $query = clone $base;

        $priceBounds = (clone $baseForFacets)->selectRaw('MIN(price) as min, MAX(price) as max')->first();
        $minBound = (int) floor($priceBounds->min ?? 0);
        $maxBound = (int) ceil($priceBounds->max ?? 0);

        if ($r->filled('min_price')) {
            $query->where('price', '>=', (float) $r->min_price);
        }
        if ($r->filled('max_price')) {
            $query->where('price', '<=', (float) $r->max_price);
        }

        $statuses = array_filter((array) $r->input('status', []));
        if ($statuses) {
            $query->whereIn('stock_status', $statuses);
        }

        $brandSlugs = array_filter((array) $r->input('brand', []));
        if ($brandSlugs) {
            $query->whereHas('brand', fn ($b) => $b->whereIn('slug', $brandSlugs));
        }

        $specFilters = array_filter((array) $r->input('spec', []), fn ($v) => ! empty($v));
        foreach ($specFilters as $name => $values) {
            if (! in_array($name, $filterAttributes, true)) {
                continue;
            }
            $values = (array) $values;
            $query->whereHas('specs', fn ($s) => $s->where('name', $name)->whereIn('value', $values));
        }

        match ($r->input('sort', 'default')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest' => $query->latest('id'),
            'popular' => $query->orderByDesc('views'),
            default => $query->orderByDesc('is_featured')->orderBy('id'),
        };

        $perPage = in_array((int) $r->input('limit'), [20, 40, 60, 80], true) ? (int) $r->input('limit') : 20;
        $products = $query->paginate($perPage)->withQueryString()->through(fn (Product $p) => $p->toCard());

        $productIds = (clone $baseForFacets)->select('products.id');
        $facets = [];
        foreach ($filterAttributes as $attr) {
            $values = ProductSpec::where('name', $attr)->whereIn('product_id', $productIds)
                ->distinct()->orderBy('value')->pluck('value');
            if ($values->isNotEmpty()) {
                $facets[] = ['name' => $attr, 'values' => $values];
            }
        }

        $brands = $withBrandFilter
            ? Brand::whereIn('id', (clone $baseForFacets)->select('brand_id'))->orderBy('name')->get(['id', 'name', 'slug'])
            : collect();

        return [
            'products' => $products,
            'filters' => [
                'priceRange' => ['min' => $minBound, 'max' => $maxBound],
                'brands' => $brands,
                'facets' => $facets,
                'statuses' => Product::STOCK_STATUSES,
                'sorts' => self::SORTS,
            ],
            'query' => [
                'min_price' => $r->input('min_price'),
                'max_price' => $r->input('max_price'),
                'status' => array_values($statuses),
                'brand' => array_values($brandSlugs),
                'spec' => (object) $specFilters,
                'sort' => $r->input('sort', 'default'),
                'limit' => $perPage,
            ],
        ];
    }
}
