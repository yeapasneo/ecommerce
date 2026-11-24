<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    /** Products per sitemap file (the protocol allows 50,000). */
    public const PER_FILE = 5000;

    public function robots()
    {
        $lines = ['User-agent: *'];
        if (Setting::enabled('seo_noindex')) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/account', '/cart', '/checkout', '/compare', '/payment', '/search', '/track-order', '/pc-builder/choose', '/login', '/register'] as $path) {
                $lines[] = "Disallow: {$path}";
            }
            // Filtered and sorted listings are duplicates of the plain category page.
            foreach (['sort', 'limit', 'spec', 'min_price', 'max_price', 'status', 'brand'] as $param) {
                $lines[] = "Disallow: /*?*{$param}";
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Sitemap index pointing at the per-type sitemaps. */
    public function index()
    {
        $xml = Cache::remember('sitemap:index', 3600, function () {
            $parts = ['pages', 'categories', 'brands'];
            $files = (int) ceil(Product::active()->count() / self::PER_FILE);
            for ($i = 1; $i <= $files; $i++) {
                $parts[] = "products-{$i}";
            }
            $lastmod = optional(Product::max('updated_at'), fn ($d) => date('c', strtotime($d)));

            $body = collect($parts)->map(fn ($p) => '<sitemap><loc>'.e(route('sitemap.part', $p)).'</loc>'.($lastmod ? "<lastmod>{$lastmod}</lastmod>" : '').'</sitemap>')->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</sitemapindex>';
        });

        return $this->xml($xml);
    }

    public function sitemap(string $type)
    {
        $xml = Cache::remember("sitemap:{$type}", 3600, function () use ($type) {
            $urls = match (true) {
                $type === 'pages' => $this->pages(),
                $type === 'categories' => Category::active()->get(['slug', 'updated_at'])->map(fn ($c) => [route('category.show', $c->slug), $c->updated_at, '0.8']),
                $type === 'brands' => Brand::where('is_active', true)->whereHas('products', fn ($q) => $q->active())->get(['slug', 'updated_at'])->map(fn ($b) => [route('brand.show', $b->slug), $b->updated_at, '0.6']),
                default => $this->products((int) substr($type, 9)),
            };
            abort_if($urls->isEmpty() && str_starts_with($type, 'products-'), 404);

            return $this->urlset($urls);
        });

        return $this->xml($xml);
    }

    protected function pages()
    {
        return collect([
            [url('/'), now(), '1.0'],
            [route('brands.index'), null, '0.5'],
            [route('offers'), null, '0.6'],
            [route('pc-builder.index'), null, '0.6'],
            [route('contact'), null, '0.4'],
        ])->concat(Page::where('is_active', true)->get(['slug', 'updated_at'])->map(fn ($p) => [route('page', $p->slug), $p->updated_at, '0.3']));
    }

    protected function products(int $file)
    {
        return Product::active()->orderBy('id')
            ->skip(($file - 1) * self::PER_FILE)->take(self::PER_FILE)
            ->get(['slug', 'name', 'image', 'updated_at'])
            ->map(fn ($p) => [route('product.show', $p->slug), $p->updated_at, '0.7', $p->image_url ? url($p->image_url) : null, $p->name]);
    }

    protected function urlset($urls): string
    {
        $body = $urls->map(function ($u) {
            [$loc, $lastmod, $priority] = $u;
            $image = isset($u[3]) && $u[3] ? '<image:image><image:loc>'.e($u[3]).'</image:loc></image:image>' : '';

            return '<url><loc>'.e($loc).'</loc>'.($lastmod ? '<lastmod>'.$lastmod->toAtomString().'</lastmod>' : '')."<priority>{$priority}</priority>{$image}</url>";
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'.$body.'</urlset>';
    }

    protected function xml(string $xml)
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
