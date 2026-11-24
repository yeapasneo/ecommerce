<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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
            ->when($request->search, fn ($q, $s) => $q->where(fn ($qq) => $qq->where('name', 'ilike', "%{$s}%")->orWhere('sku', 'ilike', "%{$s}%")))
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
            'product' => $product->load('images', 'specs'),
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
        ];
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
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:4096'],
            'specs' => ['nullable', 'array'],
            'specs.*.group' => ['nullable', 'string', 'max:100'],
            'specs.*.name' => ['required_with:specs.*.value', 'nullable', 'string', 'max:255'],
            'specs.*.value' => ['required_with:specs.*.name', 'nullable', 'string', 'max:255'],
        ]);

        $data['slug'] = Str::slug(($data['slug'] ?? null) ?: $data['name']);
        if (Product::where('slug', $data['slug'])->whereKeyNot($product?->id)->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(4));
        }
        $data['key_features'] = array_values(array_filter($data['key_features'] ?? [], fn ($f) => trim((string) $f) !== ''));

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
        $sort = (int) $product->images()->max('sort_order');
        foreach ($request->file('gallery', []) as $file) {
            $product->images()->create(['path' => $file->store('products', 'public'), 'sort_order' => ++$sort]);
        }
    }
}
