<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiClient;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category:id,name', 'brand:id,name')
            ->when($request->search, fn ($q, $s) => ProductSearch::apply($q, $s))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->brand_id, fn ($q, $b) => $q->where('brand_id', $b))
            ->when($request->stock_status, fn ($q, $s) => $q->where('stock_status', $s))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $request->only('search', 'category_id', 'brand_id', 'stock_status', 'per_page'),
            'statusCounts' => Product::selectRaw('stock_status, count(*) as total')->groupBy('stock_status')->pluck('total', 'stock_status'),
            ...$this->options(),
        ]);
    }

    /** Product picker for staff orders (JSON). */
    public function search(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('orders') || $user->hasPermission('catalog') || $user->hasPermission('marketing'), 403);
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $products = ProductSearch::rank(ProductSearch::apply(Product::active(), $term), $term)
            ->orderByRaw("case when stock_status = 'in_stock' then 0 else 1 end")
            ->orderBy('name')->take(12)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->get(['id', 'name', 'sku', 'price', 'stock', 'stock_status', 'image', 'is_active', 'has_variants']);

        // Products with options are listed once per option, so staff pick the exact one.
        return response()->json($products->flatMap(fn ($p) => $p->has_variants && $p->variants->isNotEmpty()
            ? $p->variants->map(fn ($v) => [
                'id' => $p->id, 'variant_id' => $v->id, 'name' => $p->name, 'variant_name' => $v->name, 'sku' => $v->sku ?: $p->sku,
                'price' => $v->basePrice(), 'stock' => $v->stock, 'stock_status' => $v->stock > 0 ? 'in_stock' : 'out_of_stock',
                'stock_label' => $v->stock > 0 ? 'In Stock' : 'Out of Stock', 'image_url' => $v->image_url ?: $p->image_url,
                'purchasable' => $v->stock > 0 || $p->stock_status === 'pre_order',
            ])
            : [[
                'id' => $p->id, 'variant_id' => null, 'name' => $p->name, 'variant_name' => null, 'sku' => $p->sku, 'price' => (float) $p->price,
                'stock' => $p->stock, 'stock_status' => $p->stock_status, 'stock_label' => $p->stock_label,
                'image_url' => $p->image_url, 'purchasable' => $p->isPurchasable(),
            ]])->take(20)->values());
    }

    public function create()
    {
        return Inertia::render('Admin/Products/Form', ['product' => null, ...$this->options()]);
    }

    public function store(Request $request)
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($this->validated($request));
            $this->syncRelations($request, $product);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => $product->load('images', 'specs', 'variants'),
            ...$this->options(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($this->validated($request, $product));
            $this->syncRelations($request, $product);
        });

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function destroyImage(Product $product, int $image)
    {
        $img = $product->images()->whereKey($image)->firstOrFail();
        Storage::disk('public')->delete($img->path);
        $img->delete();

        return back()->with('success', 'Image removed.');
    }

    protected function options(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'stockStatuses' => Product::STOCK_STATUSES,
            'aiReady' => app(AiClient::class)->isConfigured(),
        ];
    }

    /** Save the option combinations and keep the product's starting price, stock and status in step. */
    protected function syncVariants(Product $product, array $rows): void
    {
        $keep = [];
        foreach ($rows as $i => $row) {
            $options = collect($row['options'] ?? [])->map(fn ($v) => trim((string) $v))->filter()->all();
            if (! $options) {
                continue;
            }
            $attrs = [
                'name' => implode(' / ', $options),
                'options' => $options,
                'sku' => ($row['sku'] ?? null) ?: null,
                'price' => (float) $row['price'],
                'regular_price' => isset($row['regular_price']) && $row['regular_price'] !== '' ? (float) $row['regular_price'] : null,
                'stock' => (int) $row['stock'],
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $i,
            ];
            $variant = ! empty($row['id']) ? $product->variants()->whereKey($row['id'])->first() : null;
            $variant ? $variant->update($attrs) : ($variant = $product->variants()->create($attrs));
            $keep[] = $variant->id;
        }
        $product->variants()->whereNotIn('id', $keep)->delete();

        $active = $product->variants()->where('is_active', true)->get();
        if ($active->isEmpty()) {
            $product->update(['has_variants' => false]);

            return;
        }
        $stock = (int) $active->sum('stock');
        $cheapest = $active->sortBy(fn ($v) => $v->basePrice())->first();
        $product->update([
            'has_variants' => true,
            'price' => $cheapest->basePrice(),
            'regular_price' => $cheapest->regular_price,
            'stock' => $stock,
            'stock_status' => in_array($product->stock_status, ['pre_order', 'up_coming'], true) ? $product->stock_status : ($stock > 0 ? 'in_stock' : 'out_of_stock'),
        ]);
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products')->ignore($product?->id)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product?->id)],
            'model' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_status' => ['required', Rule::in(array_keys(Product::STOCK_STATUSES))],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'key_features' => ['nullable', 'array'],
            'key_features.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'key_features_bn' => ['nullable', 'array'],
            'key_features_bn.*' => ['nullable', 'string', 'max:255'],
            'description_bn' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:4096'],
            'specs' => ['nullable', 'array'],
            'specs.*.group' => ['nullable', 'string', 'max:100'],
            'specs.*.name' => ['required_with:specs.*.value', 'nullable', 'string', 'max:255'],
            'specs.*.value' => ['required_with:specs.*.name', 'nullable', 'string', 'max:255'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.options' => ['required_with:variants', 'array', 'min:1'],
            'variants.*.options.*' => ['required', 'string', 'max:60'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.regular_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ], [
            'variants.*.price.required_with' => 'Every option needs a price.',
            'variants.*.stock.required_with' => 'Every option needs a stock quantity.',
        ]);
        unset($data['variants']);

        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        if (Product::where('slug', $data['slug'])->whereKeyNot($product?->id)->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(4));
        }
        $data['key_features'] = array_values(array_filter($data['key_features'] ?? [], fn ($f) => trim((string) $f) !== ''));
        if (array_key_exists('key_features_bn', $data)) {
            $data['key_features_bn'] = array_values(array_filter($data['key_features_bn'] ?? [], fn ($f) => trim((string) $f) !== '')) ?: null;
        }

        if ($request->hasFile('image')) {
            if ($product?->image && ! Str::startsWith($product->image, ['http', '/'])) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        return collect($data)->except(['specs', 'gallery'])->all();
    }

    protected function syncRelations(Request $request, Product $product): void
    {
        if ($request->has('specs')) {
            $product->specs()->delete();
            foreach (array_values($request->input('specs', [])) as $i => $spec) {
                if (trim((string) ($spec['name'] ?? '')) === '' || trim((string) ($spec['value'] ?? '')) === '') {
                    continue;
                }
                $product->specs()->create([
                    'group' => trim($spec['group'] ?? '') ?: 'General',
                    'name' => trim($spec['name']),
                    'value' => trim($spec['value']),
                    'sort_order' => $i,
                ]);
            }
        }
        if ($request->has('variants') || $request->boolean('variants_cleared')) {
            $this->syncVariants($product, array_values($request->input('variants', [])));
        }
        $sort = (int) $product->images()->max('sort_order');
        foreach ($request->file('gallery', []) as $file) {
            $product->images()->create(['path' => $file->store('products', 'public'), 'sort_order' => ++$sort]);
        }
    }
}
