<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PcComponent;
use App\Models\Product;
use App\Support\PlaceholderImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the catalog from database/data/catalog.json.gz (produced by scripts/crawler/crawl.py):
 * nested categories, brands, products, key features and specifications.
 * Product images are generated placeholders; upload real photos from the admin panel.
 */
class ImportedCatalogSeeder extends Seeder
{
    public const FILE = 'data/catalog.json.gz';

    protected array $icons = [
        'appliance' => 'tv', 'desktop' => 'desktop', 'laptop' => 'laptop', 'component' => 'cpu', 'monitor' => 'monitor',
        'power' => 'power', 'phone' => 'phone', 'tablet' => 'tablet', 'office' => 'desktop', 'camera' => 'camera',
        'security' => 'camera', 'networking' => 'router', 'software' => 'box', 'server' => 'storage', 'accessories' => 'keyboard',
        'gadget' => 'headphone', 'gaming' => 'gamepad', 'lifestyle' => 'box', 'processor' => 'cpu', 'motherboard' => 'board',
        'ram' => 'memory', 'graphics' => 'gpu', 'ssd' => 'storage', 'hdd' => 'storage', 'hard disk' => 'storage',
        'power supply' => 'power', 'casing' => 'case', 'cooler' => 'fan', 'keyboard' => 'keyboard', 'mouse' => 'mouse',
        'headphone' => 'headphone', 'speaker' => 'speaker', 'router' => 'router', 'tv' => 'tv', 'television' => 'tv', 'webcam' => 'camera',
    ];

    /** @var array<string, int> path key => category id */
    protected array $categoryIds = [];

    protected array $usedSlugs = [];

    public function run(): void
    {
        $path = database_path(self::FILE);
        if (! is_file($path)) {
            $this->command?->warn('No crawled catalog found at database/'.self::FILE.'. Skipping.');

            return;
        }
        $products = json_decode(gzdecode(file_get_contents($path)), true)['products'] ?? [];
        $this->command?->info('Importing '.count($products).' products…');
        Storage::disk('public')->makeDirectory('products');

        $brandIds = $this->brands($products);
        $this->categories($products);

        $skus = [];
        foreach (array_chunk($products, 300) as $chunk) {
            DB::transaction(function () use ($chunk, $brandIds, &$skus) {
                $specRows = [];
                foreach ($chunk as $p) {
                    $categoryId = $this->categoryIds[$this->pathKey($p['category_path'])] ?? null;
                    if (! $categoryId || empty($p['name'])) {
                        continue;
                    }
                    $product = $this->product($p, $categoryId, $brandIds[$p['brand'] ?? ''] ?? null, $skus);
                    foreach ($p['specs'] ?? [] as $i => $s) {
                        $specRows[] = ['product_id' => $product->id, 'group' => $s['group'], 'name' => $s['name'], 'value' => $s['value'], 'sort_order' => $i];
                    }
                }
                foreach (array_chunk($specRows, 1000) as $rows) {
                    DB::table('product_specs')->insert($rows);
                }
            });
        }

        $this->filterAttributes();
        $this->pcComponents();
        $this->command?->info('Imported '.Product::count().' products, '.Category::count().' categories, '.Brand::count().' brands.');
    }

    protected function pathKey(array $path): string
    {
        return mb_strtolower(implode(' > ', $path));
    }

    protected function uniqueSlug(string $base, string $table): string
    {
        $slug = Str::slug($base) ?: Str::lower(Str::random(6));
        $candidate = $slug;
        $n = 2;
        while (isset($this->usedSlugs[$table][$candidate]) || DB::table($table)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$n++;
        }
        $this->usedSlugs[$table][$candidate] = true;

        return $candidate;
    }

    protected function brands(array $products): array
    {
        $ids = [];
        foreach (array_unique(array_filter(array_map(fn ($p) => trim($p['brand'] ?? ''), $products))) as $name) {
            $brand = Brand::firstOrCreate(['name' => $name], ['slug' => $this->uniqueSlug($name, 'brands'), 'is_active' => true]);
            $ids[$name] = $brand->id;
        }

        return $ids;
    }

    protected function icon(string $name, ?string $fallback): string
    {
        $lower = mb_strtolower($name);
        foreach ($this->icons as $needle => $icon) {
            if (str_contains($lower, $needle)) {
                return $icon;
            }
        }

        return $fallback ?? 'box';
    }

    protected function categories(array $products): void
    {
        $paths = [];
        foreach ($products as $p) {
            if (! empty($p['category_path'])) {
                $paths[$this->pathKey($p['category_path'])] = $p['category_path'];
            }
        }
        ksort($paths);
        $order = [];
        foreach ($paths as $path) {
            $parentId = null;
            $parentIcon = null;
            foreach ($path as $depth => $name) {
                $key = $this->pathKey(array_slice($path, 0, $depth + 1));
                if (! isset($this->categoryIds[$key])) {
                    $parentSlug = $parentId ? Category::find($parentId)?->slug : null;
                    $slugBase = Category::where('slug', Str::slug($name))->exists() && $parentSlug ? "{$parentSlug} {$name}" : $name;
                    $order[$parentId ?? 0] = ($order[$parentId ?? 0] ?? 0) + 1;
                    $cat = Category::create([
                        'parent_id' => $parentId,
                        'name' => $name,
                        'slug' => $this->uniqueSlug($slugBase, 'categories'),
                        'icon' => $this->icon($name, $parentIcon),
                        'description' => "Shop {$name} at the best price in Bangladesh with official warranty.",
                        'sort_order' => $order[$parentId ?? 0],
                        'is_active' => true,
                        'is_featured' => $depth === 0,
                    ]);
                    $this->categoryIds[$key] = $cat->id;
                }
                $parentId = $this->categoryIds[$key];
                $parentIcon = Category::find($parentId)->icon;
            }
        }
    }

    protected function status(string $raw, ?float $price): string
    {
        $raw = mb_strtolower($raw);

        return match (true) {
            ! $price => 'up_coming',
            str_contains($raw, 'in stock') => 'in_stock',
            str_contains($raw, 'pre') => 'pre_order',
            str_contains($raw, 'up coming'), str_contains($raw, 'upcoming') => 'up_coming',
            default => 'out_of_stock',
        };
    }

    protected function warrantyMonths(array $specs): ?int
    {
        foreach ($specs as $s) {
            if (stripos($s['name'], 'warranty') !== false && preg_match('/(\d+)\s*(year|month)/i', $s['value'], $m)) {
                return stripos($m[2], 'year') === 0 ? (int) $m[1] * 12 : (int) $m[1];
            }
        }

        return null;
    }

    protected function product(array $p, int $categoryId, ?int $brandId, array &$skus): Product
    {
        $price = (float) ($p['price'] ?? 0);
        $status = $this->status($p['status'] ?? '', $price ?: null);
        $sku = $p['code'] ?: strtoupper(Str::random(8));
        while (isset($skus[$sku])) {
            $sku .= '-'.strtoupper(Str::random(2));
        }
        $skus[$sku] = true;

        $slug = $this->uniqueSlug($p['slug'] ?: $p['name'], 'products');
        $category = Category::find($categoryId);
        $imagePath = "products/{$slug}.svg";
        Storage::disk('public')->put($imagePath, PlaceholderImage::svg($p['name'], $category->icon ?? 'box', $p['brand'] ?? ''));

        $features = $p['key_features'] ?: array_map(fn ($s) => "{$s['name']}: {$s['value']}", array_slice($p['specs'] ?? [], 0, 4));
        $brand = $p['brand'] ?? null;

        return Product::create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => Str::limit($p['name'], 250, ''),
            'slug' => $slug,
            'sku' => $sku,
            'model' => $brand ? trim(Str::after($p['name'], $brand)) ?: null : null,
            'price' => $price,
            'regular_price' => ($p['regular_price'] ?? 0) > $price ? $p['regular_price'] : null,
            'stock' => $status === 'in_stock' ? random_int(3, 50) : 0,
            'stock_status' => $status,
            'key_features' => array_values(array_slice($features, 0, 8)),
            'description' => '<p>'.e($p['name']).' is available at the best price with official '.e($brand ?: 'brand').' warranty. '
                .'Order online for fast home delivery anywhere in Bangladesh, or pick it up from our showroom.</p>',
            'image' => $imagePath,
            'warranty_months' => $this->warrantyMonths($p['specs'] ?? []),
            'is_active' => true,
            'is_featured' => $status === 'in_stock' && random_int(1, 100) <= 8,
            'views' => random_int(0, 3000),
        ]);
    }

    /** Pick spec names that work well as filters for each category (common, few distinct values). */
    protected function filterAttributes(): void
    {
        foreach (Category::all() as $category) {
            $ids = $category->descendantIds();
            $total = Product::whereIn('category_id', $ids)->count();
            if ($total < 4) {
                continue;
            }
            $stats = DB::table('product_specs')
                ->join('products', 'products.id', '=', 'product_specs.product_id')
                ->whereIn('products.category_id', $ids)
                ->where('product_specs.name', 'not ilike', '%warranty%')
                ->selectRaw('product_specs.name, COUNT(DISTINCT product_specs.product_id) as products, COUNT(DISTINCT product_specs.value) as vals')
                ->groupBy('product_specs.name')
                ->get()
                ->filter(fn ($r) => $r->products >= max(3, $total * 0.5) && $r->vals >= 2 && $r->vals <= 20)
                ->sortByDesc('products')
                ->take(5)
                ->pluck('name')
                ->values()
                ->all();
            if ($stats) {
                $category->update(['filter_attributes' => $stats]);
            }
        }
    }

    protected function pcComponents(): void
    {
        $map = [
            ['CPU', ['processor'], true, 'core'], ['CPU Cooler', ['cpu cooler', 'cooler'], false, 'core'],
            ['Motherboard', ['motherboard'], true, 'core'], ['RAM', ['desktop ram', 'ram'], true, 'core'],
            ['Storage', ['ssd', 'hard disk', 'hdd'], true, 'core'], ['Graphics Card', ['graphics card'], false, 'core'],
            ['Power Supply', ['power supply'], true, 'core'], ['Casing', ['casing'], true, 'core'],
            ['Monitor', ['monitor'], false, 'peripherals'], ['Keyboard', ['keyboard'], false, 'peripherals'],
            ['Mouse', ['mouse'], false, 'peripherals'], ['Headphone', ['headphone'], false, 'peripherals'],
        ];
        PcComponent::query()->delete();
        foreach ($map as $i => [$name, $needles, $required, $section]) {
            $categoryId = null;
            foreach ($needles as $needle) {
                $categoryId = Category::whereRaw('lower(name) = ?', [$needle])->orderBy('parent_id')->value('id');
                if ($categoryId) {
                    break;
                }
            }
            PcComponent::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $categoryId, 'is_required' => $required, 'section' => $section, 'sort_order' => $i]);
        }
    }
}
