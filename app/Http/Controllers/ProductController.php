<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Question;
use App\Models\Review;
use App\Models\Setting;
use App\Services\ProductAlerts;
use App\Support\Analytics;
use App\Support\Emi;
use App\Support\Phone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $product = Product::active()->where('slug', $slug)
            ->with(['brand:id,name,slug', 'category', 'images', 'specs'])->firstOrFail();
        $product->increment('views');
        // Bangla storefront: show the Bangla texts when the product has them.
        if (app()->getLocale() === 'bn') {
            $product->description = $product->description_bn ?: $product->description;
            $product->key_features = $product->key_features_bn ?: $product->key_features;
        }
        $product->makeHidden(['description_bn', 'key_features_bn']);

        $reviews = $product->reviews()->where('is_approved', true)->with('user:id,name')->latest()->get();
        $user = auth()->user();
        $this->seo($product, $reviews);
        if (session('analytics_skip_view') !== $product->id) {
            analytics()->push('view_item', ['currency' => 'BDT', 'value' => (float) $product->price, 'items' => [Analytics::item($product)]]);
        }

        return Inertia::render('Product/Show', [
            'product' => $product,
            'breadcrumbs' => $product->category->breadcrumbs(),
            'variants' => $product->has_variants
                ? $product->variants()->where('is_active', true)->get()->each->setRelation('product', $product)
                    ->map(fn ($v) => [
                        'id' => $v->id, 'name' => $v->name, 'options' => $v->options, 'sku' => $v->sku ?: $product->sku,
                        'price' => $v->price, 'regular_price' => $product->flashSale() ? max($v->basePrice(), (float) $v->regular_price) : $v->regular_price,
                        'stock' => $v->stock, 'image_url' => $v->image_url, 'available' => $v->isPurchasable(),
                    ])->values()
                : [],
            'flash' => $product->flashSale(),
            'notifyEnabled' => Setting::enabled('back_in_stock_enabled'),
            'emi' => Emi::plans((float) $product->price),
            'specGroups' => $product->specs->groupBy('group')->map(fn ($g, $name) => ['group' => $name, 'items' => $g->values()])->values(),
            'reviews' => $reviews,
            'rating' => ['average' => round((float) $reviews->avg('rating'), 1), 'count' => $reviews->count()],
            'questions' => $product->questions()->with('user:id,name')->latest()->get(),
            'related' => Product::active()->where('category_id', $product->category_id)->whereKeyNot($product->id)
                ->inRandomOrder()->take(6)->get()->map->toCard(),
            'inWishlist' => $user ? $user->wishlist()->where('product_id', $product->id)->exists() : false,
            'inCompare' => in_array($product->id, session('compare', [])),
            'hasReviewed' => $user ? $product->reviews()->where('user_id', $user->id)->exists() : false,
        ]);
    }

    protected function seo(Product $product, $reviews): void
    {
        $url = route('product.show', $product->slug);
        $price = (float) $product->price;
        $availability = match ($product->stock_status) {
            'in_stock' => 'https://schema.org/InStock',
            'pre_order' => 'https://schema.org/PreOrder',
            'up_coming' => 'https://schema.org/PreSale',
            default => 'https://schema.org/OutOfStock',
        };
        $features = implode(', ', array_slice($product->key_features ?? [], 0, 4));
        // Longest wording that fits in ~60 characters (mirrored in Admin/Products/Form.jsx).
        $autoTitle = $price > 0
            ? collect([$product->name.' Price in Bangladesh', $product->name.' Price in BD'])->first(fn ($t) => mb_strlen($t) <= 60, $product->name)
            : $product->name;

        seo()->title($product->meta_title ?: $autoTitle)
            ->description($product->meta_description ?: trim($product->name.($price > 0 ? ' price in BD: ৳'.number_format($price).'.' : '.').' '.$features))
            ->keywords($product->meta_keywords)
            ->image($product->image_url)
            ->type('product')
            ->canonical($url)
            ->meta('product:price:amount', $price > 0 ? number_format($price, 2, '.', '') : null)
            ->meta('product:price:currency', $price > 0 ? 'BDT' : null)
            ->meta('product:availability', $product->stock_status === 'in_stock' ? 'in stock' : 'out of stock')
            ->meta('product:brand', $product->brand?->name)
            ->breadcrumbs([
                ...array_map(fn ($c) => ['name' => $c['name'], 'url' => route('category.show', $c['slug'])], $product->category->breadcrumbs()),
                ['name' => $product->name, 'url' => $url],
            ])
            ->schema(array_filter([
                '@type' => 'Product',
                'name' => $product->name,
                'sku' => $product->sku,
                'mpn' => $product->model,
                'url' => $url,
                'image' => array_values(array_filter([$product->image_url ? url($product->image_url) : null, ...$product->images->map(fn ($i) => url($i->url))])),
                'description' => strip_tags($product->meta_description ?: ($features ?: $product->description)),
                'category' => $product->category->name,
                'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
                'offers' => $price > 0 ? [
                    '@type' => 'Offer',
                    'url' => $url,
                    'priceCurrency' => 'BDT',
                    'price' => number_format($price, 2, '.', ''),
                    'availability' => $availability,
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'seller' => ['@type' => 'Organization', 'name' => Setting::get('site_name')],
                ] : null,
                'aggregateRating' => $reviews->count() > 0 ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round((float) $reviews->avg('rating'), 1),
                    'reviewCount' => $reviews->count(),
                ] : null,
                'review' => $reviews->take(5)->map(fn ($r) => [
                    '@type' => 'Review',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating],
                    'author' => ['@type' => 'Person', 'name' => $r->user?->name ?? 'Customer'],
                    'reviewBody' => $r->comment,
                ])->values()->all() ?: null,
            ]));
    }

    public function review(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        Review::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            $data + ['is_approved' => false]
        );

        return back()->with('success', 'Thanks! Your review will appear after moderation.');
    }

    public function question(Request $request, Product $product)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:1000']]);
        Question::create($data + ['product_id' => $product->id, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Your question has been submitted. We will answer it soon.');
    }

    /** "Notify me when it's back": phone or email (signed-in shoppers can use their account's). */
    public function notifyMe(Request $request, Product $product, ProductAlerts $alerts)
    {
        abort_unless($product->is_active && Setting::enabled('back_in_stock_enabled'), 404);
        $data = $request->validate([
            'variant_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
        ], ['phone.required_without' => 'Enter your mobile number or email.', 'email.required_without' => 'Enter your mobile number or email.']);
        if (! empty($data['phone']) && ! Phone::isValid($data['phone'])) {
            return back()->withErrors(['phone' => 'Enter a valid Bangladeshi mobile number.']);
        }
        $variant = ! empty($data['variant_id']) ? $product->variants()->findOrFail($data['variant_id']) : null;
        $alerts->subscribe($product, $variant, $data['phone'] ?? null, $data['email'] ?? null, $request->user());

        return back()->with('success', "We'll let you know as soon as it's back in stock.");
    }
}
